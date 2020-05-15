<?php

// vim:ts=4:sw=4:et:fdm=marker:fdl=0

namespace atk4\filestore\Field;

class File extends \atk4\data\Field_SQL
{
    use \atk4\core\InitializerTrait {
        init as _init;
    }


    public $ui = ['form'=>'\atk4\filestore\FormField\Upload'];

    /**
     * Set a custom model for File
     */
    public $model = null;

    /**
     * Will contain path of the file while it's stored locally
     *
     * @var string
     */
    public $localField = null;

    public $flysystem = null;

    public $normalizedField = null;

    public $reference;

    public $fieldFilename;
    public $fieldURL;

    public function init(): void
    {
        $this->_init();

        if (!$this->model) {
            $this->model = new \atk4\filestore\Model\File($this->owner->persistence);
            $this->model->flysystem = $this->flysystem;
        }

        $this->normalizedField = preg_replace('/_id$/', '', $this->short_name);
        $this->reference = $this->owner->hasOne($this->short_name, [$this->model, 'their_field'=>'token']);

        $this->importFields();

        $this->owner->onHook('beforeSave', function ($model) {
            if ($model->isDirty($this->short_name)) {
                $old = $model->dirty[$this->short_name];
                $new = $model[$this->short_name];

                // remove old file, we don't need it
                if ($old) {
                    $model->refModel($this->short_name)->loadBy('token', $old)->delete();
                }

                // mark new file as linked
                if ($new) {
                    $model->refModel($this->short_name)->loadBy('token', $new)->save(['status' => 'linked']);
                }
            }
        });
        $this->owner->onHook('beforeDelete', function ($model) {
            $token = $model[$this->short_name];
            if ($token) {
                $model->refModel($this->short_name)->loadBy('token', $token)->delete();
            }
        });
    }

    public function importFields()
    {
        //$this->reference->addField($this->normalizedField.'_token', 'token');
        $this->fieldURL = $this->reference->addField($this->normalizedField . '_url', 'url');
        $this->fieldFilename = $this->reference->addField($this->normalizedField . '_filename', 'meta_filename');
    }

    function __construct(\League\Flysystem\Filesystem $flysystem) {
        $this->flysystem = $flysystem;
    }

    public function normalize($value)
    {
        return parent::normalize($value);
    }
}
