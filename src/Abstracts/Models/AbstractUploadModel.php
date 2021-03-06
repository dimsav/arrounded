<?php
namespace Arrounded\Abstracts\Models;

use Codesleeve\Stapler\Attachment;
use Codesleeve\Stapler\AttachmentConfig;
use Codesleeve\Stapler\ORM\EloquentTrait;
use Codesleeve\Stapler\ORM\StaplerableInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\HTML;
use Illuminate\Support\Str;

/**
 * @property Attachment file
 */
abstract class AbstractUploadModel extends AbstractModel implements StaplerableInterface
{
    use EloquentTrait;

    /**
     * The attributes that are mass assignable.
     *
     * @type array
     */
    protected $fillable = [
        'file',
        'type',
        'illustrable_id',
        'illustrable_type',
    ];

    /**
     * @type array
     */
    protected $appends = ['thumbs'];

    /**
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->hasAttachedFile('file', ['styles' => $this->getThumbnailsConfiguration()]);

        parent::__construct($attributes);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function illustrable()
    {
        return $this->morphTo();
    }

    /**
     * @return bool
     */
    public function hasIllustrable()
    {
        return strpos($this->illustrable_type, 'Temporary') === false && $this->illustrable;
    }

    /**
     * Call a method on the Attachment object.
     *
     * @param string $method
     * @param array  $parameters
     *
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        if (method_exists($this->file, $method)) {
            return call_user_func_array([$this->file, $method], $parameters);
        }

        return parent::__call($method, $parameters);
    }

    //////////////////////////////////////////////////////////////////////
    /////////////////////////////// SCOPES ///////////////////////////////
    //////////////////////////////////////////////////////////////////////

    /**
     * Scope to only the illustrables of an instance.
     *
     * @param Builder       $query
     * @param AbstractModel $model
     *
     * @return Builder
     */
    public function scopeIllustrable($query, AbstractModel $model)
    {
        return $query->where([
            'illustrable_type' => $model->getClass(),
            'illustrable_id'   => $model->id,
        ]);
    }

    /**
     * Scope to only images.
     *
     * @param Builder $query
     *
     * @return Builder
     */
    public function scopeWhereImages($query)
    {
        return $query->where('file_content_type', 'LIKE', 'image/%');
    }

    //////////////////////////////////////////////////////////////////////
    /////////////////////////////// THUMBS ///////////////////////////////
    //////////////////////////////////////////////////////////////////////

    /**
     * Check if the bound file is an image.
     *
     * @return bool
     */
    public function isImage()
    {
        return Str::startsWith($this->file_content_type, 'image/');
    }

    /**
     * Get an array of the image's thumbs.
     *
     * @return array
     */
    public function getThumbsAttribute()
    {
        if (!$this->isImage()) {
            return [];
        }

        $config = $this->getImageConfig();
        $this->file->setConfig($config);

        // Fetch path to thumbnails
        $thumbs = [];
        foreach ($this->file->styles as $style) {
            $thumbs[$style->name] = $this->file->url($style->name);
        }

        return $thumbs;
    }

    /**
     * Reprocess the styles. This will create all the styles for the current image.
     */
    public function reprocessStyles()
    {
        // styles only apply to images
        if (!$this->isImage()) {
            return;
        }

        $config = $this->getImageConfig();
        $this->file->setConfig($config);

        // Reprocess thumbnails
        $this->file->reprocess();
    }

    /**
     * @return AttachmentConfig
     */
    protected function getImageConfig()
    {
        // Get base configuration
        $config = Config::get('laravel-stapler::stapler');
        $config += Config::get('laravel-stapler::filesystem');

        // Set styles
        $config['styles']             = $this->getThumbnailsConfiguration();
        $config['styles']['original'] = '';

        return new AttachmentConfig('file', $config);
    }

    /**
     * Renders the image at a certain size.
     *
     * @param string|null $size
     * @param array       $attributes
     *
     * @return string
     */
    public function render($size = null, $attributes = [])
    {
        $url  = $this->file->url($size);
        $path = $this->file->path();
        if (!file_exists($path)) {
            $type = $this->hasIllustrable() ? $this->illustrable->getClassBasename() : null;
            $url  = static::getPlaceholder($type);
        }

        return HTML::image($url, null, $attributes);
    }

    //////////////////////////////////////////////////////////////////////
    ////////////////////////////// HELPERS ///////////////////////////////
    //////////////////////////////////////////////////////////////////////

    /**
     * Get the placeholder image.
     *
     * @param string|null $type
     *
     * @return string|null
     */
    public static function getPlaceholder($type = null)
    {
        return;
    }

    /**
     * Get the available thumbnail sizes.
     *
     * @return array
     */
    abstract protected function getThumbnailsConfiguration();
}
