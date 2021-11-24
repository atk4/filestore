<?php

declare(strict_types=1);

namespace Atk4\Filestore\Demos;

use Atk4\Data\Model;
use Atk4\Filestore\Field\FileField;
use League\Flysystem\Filesystem;

class Friend extends Model
{
    public $table = 'friend';

    /** @var Filesystem */
    public $filesystem;

    protected function init(): void
    {
        parent::init();

        $this->addField('name', ['required' => true]);
        $this->addField('file', [FileField::class, ['flysystem' => $this->filesystem]]);
        $this->addField('file2', [FileField::class, ['flysystem' => $this->filesystem]]);
    }
}
