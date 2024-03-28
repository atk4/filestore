<?php

declare(strict_types=1);

namespace Atk4\Filestore\Demos;

use Atk4\Data\Model;
use Atk4\Filestore\Field\FileField;
use Atk4\Filestore\Form\Control\Upload;
use League\Flysystem\Filesystem;

class Friend extends Model
{
    public $table = 'friend';

    public $caption = 'Friend';

    /** @var Filesystem */
    public $filesystem;

    protected function init(): void
    {
        parent::init();

        $this->addField('name', ['required' => true]);

        $this->addField('file1', [
            FileField::class,
            [
                'ui' => ['form' => [Upload::class, 'accept' => ['.jpg', '.png', '.gif']]],
                'caption' => 'Photo',
                'flysystem' => $this->filesystem,
            ],
        ]);

        $this->addField('file2', [
            FileField::class,
            [
                'caption' => 'Document',
                'flysystem' => $this->filesystem,
            ],
        ]);
    }
}
