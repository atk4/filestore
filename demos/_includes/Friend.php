<?php

declare(strict_types=1);

namespace Atk4\Filestore\Demos;

use Atk4\Data\Model;
use Atk4\Filestore\Field\File;

class Friend extends Model
{
    public $table = 'friend';

    /** @var TODO */
    public $filesystem;

    protected function init(): void
    {
        parent::init();

        $this->addField('name');                               // friend's name
        $this->addField('file', new File($this->filesystem));  // storing file here
        $this->addField('file2', new File($this->filesystem)); // storing file here
    }
}
