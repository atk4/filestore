<?php

declare(strict_types=1);

namespace Atk4\Filestore\Form\Control;

use Atk4\Data\Model;
use Atk4\Data\Model\EntityFieldPair;
use Atk4\Filestore\Field\FileField;
use Atk4\Ui\Js\JsExpressionable;

/**
 * @phpstan-property EntityFieldPair<Model, FileField> $entityField
 */
class Upload extends \Atk4\Ui\Form\Control\Upload
{
    protected function init(): void
    {
        parent::init();

        $this->onUpload(\Closure::fromCallable([$this, 'uploaded']));
        $this->onDelete(\Closure::fromCallable([$this, 'deleted']));
    }

    /**
     * @param array<string, mixed> $file
     */
    protected function uploaded(array $file): ?JsExpressionable
    {
        // provision a new file for specified flysystem
        $model = $this->entityField->getField()->fileModel;
        $entity = $model->createFromPath($file['tmp_name'], $file['name']);

        $this->setFileId($entity->get('token'));

        return null;
    }

    protected function deleted(string $token): ?JsExpressionable
    {
        $model = $this->entityField->getField()->fileModel;
        $entity = $model->loadBy('token', $token);

        if ($entity->get('status') === $entity::STATUS_DRAFT) {
            $entity->delete();
        }

        return null;
    }

    protected function renderView(): void
    {
        if ($this->entityField->getField()->fieldFilename) { // @phpstan-ignore-line
            $this->set(
                $this->entityField->get(),
                $this->entityField->getField()->fieldFilename->get($this->entityField->getEntity())
            );
        }

        parent::renderView();
    }
}
