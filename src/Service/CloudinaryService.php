<?php

namespace App\Service;

use Cloudinary\Cloudinary;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class CloudinaryService
{
    private Cloudinary $cloudinary;
    private string $preset;

    public function __construct(
        string $cloudName,
        string $apiKey,
        string $apiSecret
    ) {
        $this->preset = 'produit';
        $this->cloudinary = new Cloudinary([
            'cloud' => [
                'cloud_name' => $cloudName,
                'api_key'    => $apiKey,
                'api_secret' => $apiSecret,
            ],
            'url' => [
                'secure' => true
            ]
        ]);
    }

    /**
     * Uploads an image to Cloudinary and returns the secure URL.
     * 
     * @param UploadedFile $file
     * @return string|null
     */
    public function uploadImage(UploadedFile $file): ?string
    {
        try {
            $upload = $this->cloudinary->uploadApi()->upload(
                $file->getRealPath(),
                [
                    // You can use the preset if it's configured for signed/unsigned uploads
                    'upload_preset' => $this->preset,
                ]
            );

            return $upload['secure_url'];
        } catch (\Exception $e) {
            // Log error if needed: $e->getMessage()
            return null;
        }
    }
}
