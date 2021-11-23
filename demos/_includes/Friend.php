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

        $this->addField('name');                               // friend's name
        $this->addField('file', new FileField($this->filesystem));  // storing file here
        $this->addField('file2', new FileField($this->filesystem)); // storing file here
    }
}
