<?php

declare(strict_types=1);

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

    public array $ui = ['form' => [Upload::class]];

    /** @var File|null */
    public $fileModel;
    /** @var Filesystem for fileModel init */
    public $flysystem;

    /** @var string */
    protected $fieldNameBase;

    /** @var HasOneSql */
    public $reference;
    /** @var Field */
    public $fieldFilename;
    /** @var Field */
    public $fieldUrl;

    protected function init(): void
    {
        $this->_init();

        if ($this->fileModel === null) {
            $this->fileModel = new File($this->getOwner()->getPersistence());
            $this->fileModel->flysystem = $this->flysystem;
        }

        $this->reference = HasOneSql::assertInstanceOf($this->getOwner()->hasOne($this->shortName, [
            'model' => $this->fileModel,
            'theirField' => 'token',
        ]));

        $this->fieldNameBase = preg_replace('/_id$/', '', $this->shortName);
        $this->importFields();

        $this->getOwner()->onHook(Model::HOOK_BEFORE_SAVE, function (Model $m) {
            if ($m->isDirty($this->shortName)) {
                $old = $m->getDirtyRef()[$this->shortName];
                $new = $m->get($this->shortName);

                // remove old file, we don't need it
                if ($old) {
                    $m->refModel($this->shortName)->loadBy('token', $old)->delete();
                }

                // mark new file as linked
                if ($new) {
                    $m->refModel($this->shortName)->loadBy('token', $new)->save(['status' => 'linked']);
                }
            }
        });

        $this->getOwner()->onHook(Model::HOOK_BEFORE_DELETE, function (Model $m) {
            $token = $m->get($this->shortName);
            if ($token) {
                $m->refModel($this->shortName)->loadBy('token', $token)->delete();
            }
        });
    }

    protected function importFields(): void
    {
        $this->fieldUrl = $this->reference->addField($this->fieldNameBase . '_url', 'url');
        $this->fieldFilename = $this->reference->addField($this->fieldNameBase . '_filename', 'meta_filename');
    }
}
