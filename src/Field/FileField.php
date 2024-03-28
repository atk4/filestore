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

    protected function init(): void
    {
        $this->_init();

        if ($this->fileModel === null) {
            $this->fileModel = new File($this->getOwner()->getPersistence());
            $this->fileModel->flysystem = $this->flysystem;
        }

        $this->reference = HasOneSql::assertInstanceOf($this->getOwner()->hasOne($this->shortName, [
            'type' => $this->fileModel->getField('token')->type, // TODO imply in https://github.com/atk4/data/blob/develop/src/Reference/HasOne.php#L27
            'model' => $this->fileModel,
            'theirField' => 'token',
        ]));

        // TODO https://github.com/atk4/ui/pull/1805
        $this->onHookToOwnerEntity(Model::HOOK_BEFORE_SAVE, function (Model $m) {
            if ($m->get($this->shortName) === '') {
                $m->set($this->shortName, null);
            }
        });

        $this->fieldNameBase = preg_replace('~_id$~', '', $this->shortName);
        $this->importFields();

        // on insert/update delete old file and mark new one as linked
        $fx = function (Model $m) {
            if ($m->isDirty($this->shortName)) {
                $old = $m->getDirtyRef()[$this->shortName];
                $new = $m->get($this->shortName);

                // remove old file, we don't need it
                if ($old) {
                    $m->getModel()->getReference($this->shortName)->createTheirModel()->loadBy('token', $old)->delete();
                }

                // mark new file as linked
                if ($new) {
                    $m->getModel()->getReference($this->shortName)->createTheirModel()->loadBy('token', $new)->save(['status' => File::STATUS_LINKED]);
                }
            }
        };
        $this->onHookToOwnerEntity(Model::HOOK_AFTER_INSERT, $fx);
        $this->onHookToOwnerEntity(Model::HOOK_AFTER_UPDATE, $fx);

        $this->onHookToOwnerEntity(Model::HOOK_AFTER_DELETE, function (Model $m) {
            $token = $m->get($this->shortName);
            if ($token) {
                $m->getModel()->getReference($this->shortName)->createTheirModel()->loadBy('token', $token)->delete();
            }
        });
    }

    protected function importFields(): void
    {
        $field = $this->getOwner()->getField($this->shortName);

        $this->fieldFilename = $this->reference->addField(
            $this->fieldNameBase . '_filename',
            'meta_filename',
            ['caption' => $field->caption ? $field->caption . ' Filename' : null]
        );
    }
}
