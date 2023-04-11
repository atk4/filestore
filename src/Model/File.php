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

    /** @const string All uploaded files first get this status */
    public const STATUS_DRAFT = 'draft';
    /** @const string When file is linked to some other model */
    public const STATUS_LINKED = 'linked';
    /** @const string Used for thumbnail files */
    public const STATUS_THUMB = 'thumb';
    /** @const array */
    public const ALL_STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_LINKED,
        self::STATUS_THUMB,
    ];

    /** @int Delay in seconds used to avoid race condition when cleaning updraft records */
    protected int $cleanupDraftsDelay = 5;

    /** @bool Should we automatically create thumbnail for images */
    public bool $createThumbnail = true;

    /** @int Thumbnail image max width in pixels */
    protected int $thumbnailMaxWidth = 150;

    /** @int Thumbnail image max height in pixels */
    protected int $thumbnailMaxHeight = 150;

    /** @var Filesystem */
    public $flysystem;

    protected function init(): void
    {
        parent::init();

        $this->addField('token', ['system' => true, 'type' => 'string', 'required' => true]);
        $this->addField('location');
        $this->addField('url'); // not implemented
        $this->addField('storage'); // not implemented
        $this->hasOne('source_file_id', [ // this field can be used to link thumb images (when we'll implement that) to source image for example
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

        // cascade-delete all related child files
        $this->onHook(Model::HOOK_BEFORE_DELETE, function (self $m) {
            $files = (clone $m->getModel())->addCondition('source_file_id', $m->getId());
            foreach ($files as $file) {
                $file->delete();
            }
        });

        // delete physical file from storage after we delete DB record
        $this->onHook(Model::HOOK_AFTER_DELETE, function (self $m) {
            $path = $m->get('location');
            if ($path && $m->flysystem && $m->flysystem->fileExists($path)) { // @phpstan-ignore-line
                $m->flysystem->delete($path);
            }
        });
    }

    /**
     * @return $this
     */
    public function newFile()
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
     * @return $this
     */
    public function createFromPath(string $path, string $fileName = null)
    {
        $this->assertIsModel();

        if ($fileName === null) {
            $fileName = basename($path);
        }
        $entity = $this->newFile();

        // store file in filesystem
        $stream = fopen($path, 'r+');
        $entity->flysystem->writeStream($entity->get('location'), $stream, ['visibility' => 'public']);
        if (is_resource($stream)) {
            fclose($stream);
        }

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

        [$width, $height, $image_type] = getimagesize($path);

        switch ($image_type) {
            case \IMAGETYPE_GIF:
                $src = imagecreatefromgif($path);

                break;
            case \IMAGETYPE_JPEG:
                $src = imagecreatefromjpeg($path);

                break;
            case \IMAGETYPE_PNG:
                $src = imagecreatefrompng($path);

                break;
            default:
                return false; // unsupported image type
        }

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
        if ($image_type === \IMAGETYPE_PNG || $image_type === \IMAGETYPE_GIF) {
            imagealphablending($tmp, false);
            imagesavealpha($tmp, true);
            $transparent = imagecolorallocatealpha($tmp, 255, 255, 255, 127);
            imagefilledrectangle($tmp, 0, 0, $tn_width, $tn_height, $transparent);
        }
        imagecopyresampled($tmp, $src, 0, 0, 0, 0, $tn_width, $tn_height, $width, $height);

        // create temporary thumb file
        $thumbFile = tmpfile();
        switch ($image_type) {
            case \IMAGETYPE_GIF:
                imagegif($tmp, $thumbFile);

                break;
            case \IMAGETYPE_JPEG:
                imagejpeg($tmp, $thumbFile, 100); // best quality

                break;
            case \IMAGETYPE_PNG:
                imagepng($tmp, $thumbFile, 0); // no compression

                break;
        }
        $uri = stream_get_meta_data($thumbFile)['uri'];

        // free up memory
        imagedestroy($src);
        imagedestroy($tmp);

        // Import it in filestore and link to this (original image) entity
        $ext = $this->get('meta_extension') ? '.' . $this->get('meta_extension') : '';
        $thumbName = basename($this->get('meta_filename'), $ext) . '.thumb' . $ext;
        $thumbModel = $this->getModel();
        $thumbModel->createThumbnail = false; // do not create thumbnails of thumbnails
        $thumbEntity = $thumbModel->createFromPath($uri, $thumbName);
        $thumbEntity->set('source_file_id', $this->getId());
        $thumbEntity->save();

        // close tmp file and it will be deleted
        fclose($thumbFile);

        return true;
    }

    /**
     * Useful method to clean up all draft files.
     * Can be called as user action or on schedule bases to clean up filestore repository.
     *
     * @return $this
     */
    public function cleanupDrafts()
    {
        $m = $this->isEntity() ? $this->getModel() : $this;

        $files = (clone $m)
            ->addCondition('status', self::STATUS_DRAFT)
            ->addCondition('created_at', '<', (new \DateTime())->sub(new \DateInterval('PT' . $this->cleanupDraftsDelay . 'S')));
        foreach ($files as $file) {
            $file->delete();
        }

        return $this;
    }
}
