<?php

declare(strict_types=1);

// vim:ts=4:sw=4:et:fdm=marker:fdl=0

namespace Atk4\Filestore\Field;

use Atk4\Core\InitializerTrait;
use Atk4\Data\FieldSql;
use atk4\data\Model;
use Atk4\Filestore\Form\Control\Upload;
use League\Flysystem\Filesystem;

class File extends FieldSql
{
    use InitializerTrait {
        init as _init;
    }

    public $ui = ['form' => [Upload::class]];

    /**
     * Set a custom model for File.
     */
    public $model;

    /**
     * Will contain path of the file while it's stored locally.
     *
     * @var string
     */
    public $localField;

    public $flysystem;

    public $normalizedField;

    public $reference;

    public $fieldFilename;
    public $fieldURL;

    public function __construct(Filesystem $flysystem)
    {
        parent::__construct([]);
        $this->flysystem = $flysystem;
    }

    public function normalize($value)
    {
        return parent::normalize($value);
    }

    protected function init(): void
    {
        $this->_init();

        if (!$this->model) {
            $this->model = new \Atk4\Filestore\Model\File($this->getOwner()->persistence);
            $this->model->flysystem = $this->flysystem;
        }

        $this->normalizedField = preg_replace('/_id$/', '', $this->short_name);
        $this->reference = $this->getOwner()->hasOne($this->short_name, [
            'model' => $this->model,
            'their_field' => 'token',
        ]);

        $this->importFields();

        $this->getOwner()->onHook(Model::HOOK_BEFORE_SAVE, function ($m) {
            if ($m->isDirty($this->short_name)) {
                $old = $m->dirty[$this->short_name];
                $new = $m->get($this->short_name);

                // remove old file, we don't need it
                if ($old) {
                    $m->refModel($this->short_name)->loadBy('token', $old)->delete();
                }

                // mark new file as linked
                if ($new) {
                    $m->refModel($this->short_name)->loadBy('token', $new)->save(['status' => 'linked']);
                }
            }
        });

        $this->getOwner()->onHook(Model::HOOK_BEFORE_DELETE, function ($m) {
            $token = $m->get($this->short_name);
            if ($token) {
                $m->refModel($this->short_name)->loadBy('token', $token)->delete();
            }
        });
    }

    public function importFields()
    {
        $this->fieldURL = $this->reference->addField($this->normalizedField . '_url', 'url');
        $this->fieldFilename = $this->reference->addField($this->normalizedField . '_filename', 'meta_filename');
    }
}
