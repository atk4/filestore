<?php

declare(strict_types=1);

// vim:ts=4:sw=4:et:fdm=marker:fdl=0

namespace Atk4\Filestore\Field;

use Atk4\Core\InitializerTrait;
use Atk4\Data\Field;
use Atk4\Data\Model;
use Atk4\Data\Reference\HasOneSql;
use Atk4\Filestore\Form\Control\Upload;
use Atk4\Filestore\Model\File;
use League\Flysystem\Filesystem;

class FileField extends Field
{
    use InitializerTrait {
        init as private _init;
    }

    public $ui = ['form' => [Upload::class]];

    /** @var File|null */
    public $model;

    /** @var string Will contain path of the file while it's stored locally. */
    public $localField;

    /** @var Filesystem */
    public $flysystem;

    /** @var string */
    public $normalizedField;

    /** @var HasOneSql */
    public $reference;

    /** @var Field */
    public $fieldFilename;

    /** @var Field */
    public $fieldUrl;

    protected function init(): void
    {
        $this->_init();

        if ($this->model === null) {
            $this->model = new File($this->getOwner()->persistence);
            $this->model->flysystem = $this->flysystem;
        }

        $this->normalizedField = preg_replace('/_id$/', '', $this->short_name);
        $this->reference = HasOneSql::assertInstanceOf($this->getOwner()->hasOne($this->short_name, [
            'model' => $this->model,
            'their_field' => 'token',
        ]));

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

    public function importFields(): void
    {
        $this->fieldUrl = $this->reference->addField($this->normalizedField . '_url', 'url');
        $this->fieldFilename = $this->reference->addField($this->normalizedField . '_filename', 'meta_filename');
    }
}