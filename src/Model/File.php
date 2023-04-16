<?php

declare(strict_types=1);

namespace Atk4\Filestore\Model;

use Atk4\Data\Model;
use League\Flysystem\Filesystem;
use League\MimeTypeDetection\FinfoMimeTypeDetector;

class File extends Model
{
    public $table = 'filestore_file';

    public ?string $titleField = 'meta_filename';

    /** All uploaded files first get this status */
    public const STATUS_DRAFT = 'draft';
    /** When file is linked to some other model */
    public const STATUS_LINKED = 'linked';
    /** @const string Used for thumbnail files */
    public const STATUS_THUMB = 'thumb';
    /** @const list<string> */
    public const ALL_STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_LINKED,
        self::STATUS_THUMB,
    ];

    /** @var Filesystem */
    public $flysystem;

    /** Should we automatically create thumbnail for images */
    public bool $createThumbnail = true;

    /** Thumbnail image max width in pixels */
    protected int $thumbnailMaxWidth = 150;

    /** Thumbnail image max height in pixels */
    protected int $thumbnailMaxHeight = 150;

    /** @var 'png'|'jpg'|'gif' Thumbnail format */
    protected string $thumbnailFormat = 'png';

    /** In seconds, to prevent cleaning up unsaved forms */
    protected int $cleanupDraftsDelay = 2 * 24 * 3600;

    protected function init(): void
    {
        parent::init();

        $this->addField('token', ['system' => true, 'type' => 'string', 'required' => true]);
        $this->addField('location');
        $this->addField('url');

        $this->hasOne('source_file_id', [ // this field can be used to link thumb images to source image
            'model' => [self::class],
        ]);

        $this->addField('status', [
            'enum' => self::ALL_STATUSES,
            'default' => self::STATUS_DRAFT,
        ]);
        $this->addField('created_at', ['type' => 'datetime', 'required' => true]);

        $this->addField('meta_filename');
        $this->addField('meta_extension');
        $this->addField('meta_md5');
        $this->addField('meta_mime_type');
        $this->addField('meta_size', ['type' => 'integer']);
        $this->addField('meta_is_image', ['type' => 'boolean']);
        $this->addField('meta_image_width', ['type' => 'integer']);
        $this->addField('meta_image_height', ['type' => 'integer']);

        $this->onHook(Model::HOOK_BEFORE_SAVE, function (self $m, bool $isUpdate) {
            if ($isUpdate === false) {
                $m->set('created_at', new \DateTime());
            }
        });

        // change status of thumbs when status of original image changes
        $this->onHook(Model::HOOK_AFTER_SAVE, function (self $m) {
            if ($m->get('status') === self::STATUS_LINKED) {
                $files = (clone $m->getModel())->addCondition('source_file_id', $m->getId());
                foreach ($files as $file) {
                    $file->set('status', self::STATUS_THUMB);
                    $file->save();
                }
            }
        });

        // cascade-delete all related child files, for example, thumbnails
        $this->onHook(Model::HOOK_BEFORE_DELETE, function (self $m) {
            $files = (clone $m->getModel())->addCondition('source_file_id', $m->getId());
            foreach ($files as $file) {
                $file->delete();
            }
        });

        // delete physical file from storage after we delete DB record
        $this->onHook(Model::HOOK_AFTER_DELETE, function (self $m) {
            $path = $m->get('location');
            if ($path && $m->flysystem->fileExists($path)) {
                $m->flysystem->delete($path);
            }
        });
    }

    /**
     * @return static
     */
    public function newFile(): Model
    {
        $this->assertIsModel();

        $entity = $this->createEntity();
        $entity->set('token', uniqid('token-'));
        $entity->set('location', uniqid('file-') . '.bin');

        return $entity;
    }

    /**
     * Create new entity from file path.
     *
     * @param string $path     Path to file to import
     * @param string $fileName Optional original file name
     *
     * @return static
     */
    public function createFromPath(string $path, string $fileName = null): Model
    {
        $this->assertIsModel();

        if ($fileName === null) {
            $fileName = basename($path);
        }

        $entity = $this->newFile();

        // store file in filesystem
        $stream = fopen($path, 'r+');
        $entity->flysystem->writeStream($entity->get('location'), $stream, ['visibility' => 'public']);
        fclose($stream);

        // detect mime-type
        $detector = new FinfoMimeTypeDetector();
        $mimeType = $detector->detectMimeTypeFromFile($path);
        $entity->set('meta_mime_type', $mimeType);

        // store meta-information
        $entity->set('meta_md5', md5_file($path));
        $entity->set('meta_filename', $fileName);
        $entity->set('meta_size', filesize($path));
        $pos = strrpos($fileName, '.');
        if ($pos !== false) {
            $ext = strtolower(substr($fileName, $pos + 1));
            if ($ext !== 'tmp') {
                $entity->set('meta_extension', $ext);
            }
        }

        // additional meta-information for images
        $imageSizeArr = getimagesize($path);
        $entity->set('meta_is_image', $imageSizeArr !== false);
        if ($imageSizeArr !== false) {
            $entity->set('meta_image_width', $imageSizeArr[0]);
            $entity->set('meta_image_height', $imageSizeArr[1]);
        }

        $entity->save();

        // create thumbnail images if needed and possible
        if ($entity->get('meta_is_image') && $this->createThumbnail === true) {
            $entity->createThumbnail($path);
        }

        return $entity;
    }

    /**
     * Create thumbnail images of current image.
     *
     * @param string $path Local path to original file
     *
     * @return bool True on success
     */
    public function createThumbnail(string $path): bool
    {
        $this->assertIsEntity();

        $max_width = $this->thumbnailMaxWidth;
        $max_height = $this->thumbnailMaxHeight;

        $src = imagecreatefromstring(file_get_contents($path));
        if ($src === false) {
            return false; // unsupported image type
        }

        [$width, $height] = getimagesize($path);
        $x_ratio = $max_width / $width;
        $y_ratio = $max_height / $height;

        if (($width <= $max_width) && ($height <= $max_height)) {
            $tn_width = $width;
            $tn_height = $height;
        } elseif (($x_ratio * $height) < $max_height) {
            $tn_height = (int) ceil($x_ratio * $height);
            $tn_width = $max_width;
        } else {
            $tn_width = (int) ceil($y_ratio * $width);
            $tn_height = $max_height;
        }

        $tmp = imagecreatetruecolor($tn_width, $tn_height);

        // Check if this image is PNG or GIF, then set if Transparent
        if ($this->thumbnailFormat === 'png' || $this->thumbnailFormat === 'gif') {
            imagealphablending($tmp, false);
            imagesavealpha($tmp, true);
            $transparent = imagecolorallocatealpha($tmp, 255, 255, 255, 127);
            imagefilledrectangle($tmp, 0, 0, $tn_width, $tn_height, $transparent);
        }
        imagecopyresampled($tmp, $src, 0, 0, 0, 0, $tn_width, $tn_height, $width, $height);

        // create temporary thumb file
        try {
            $thumbFile = tmpfile();
            switch ($this->thumbnailFormat) {
                case 'gif':
                    imagegif($tmp, $thumbFile);
                    $ext = image_type_to_extension(\IMAGETYPE_GIF);

                    break;
                case 'jpg':
                    imagejpeg($tmp, $thumbFile);
                    $ext = image_type_to_extension(\IMAGETYPE_JPEG);

                    break;
                case 'png':
                    imagepng($tmp, $thumbFile);
                    $ext = image_type_to_extension(\IMAGETYPE_PNG);

                    break;
                default:
                    return false; // unsupported thumbnail format
            }
            $uri = stream_get_meta_data($thumbFile)['uri'];

            // free up memory
            imagedestroy($src);
            imagedestroy($tmp);

            // Import it in filestore and link to this (original image) entity
            $thumbName = basename($this->get('meta_filename'), $ext) . '.thumb' . $ext;
            $thumbModel = $this->getModel();
            $thumbModel->createThumbnail = false; // do not create thumbnails of thumbnails
            $thumbEntity = $thumbModel->createFromPath($uri, $thumbName);
            $thumbEntity->save(['source_file_id' => $this->getId()]);
        } finally {
            // close tmp file and it will be deleted
            fclose($thumbFile);
        }

        return true;
    }

    /**
     * Useful method to clean up all draft files.
     * Can be called as user action or on schedule bases to clean up filestore repository.
     */
    public function cleanupDrafts(): void
    {
        $this->getPersistence()->atomic(function () {
            $files = (clone $this)
                ->addCondition('status', self::STATUS_DRAFT)
                ->addCondition('created_at', '<', (new \DateTime())->sub(new \DateInterval('PT' . $this->cleanupDraftsDelay . 'S')));

            foreach ($files as $file) {
                $file->delete();
            }
        });
    }
}
