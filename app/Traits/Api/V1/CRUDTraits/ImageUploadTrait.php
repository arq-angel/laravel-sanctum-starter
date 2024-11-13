<?php

namespace App\Traits\Api\V1\CRUDTraits;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

trait ImageUploadTrait
{
    /**
     * @param Request $request
     * @param $inputName
     * @param $path
     * @return string|null
     */
    public function uploadImage(Request $request, $inputName, $path): string|null
    {
        if ($request->hasFile($inputName)) {
            $image = $request->{$inputName};
            $ext = $image->getClientOriginalExtension();
            $imageName = 'media_' . uniqid() . '.' . $ext;

            // Store the file on the specified disk and path
            $storedPath = $image->storeAs($path, $imageName, 'public');

            return $storedPath; // Return the relative file path
        }

        return null; // Return null if no file is uploaded
    }

    /**
     * @param Request $request
     * @param $inputName
     * @param $path
     * @param $oldPath
     * @return string|void
     */
    public function updateImage(Request $request, $inputName, $path, $oldPath = null): string|null
    {
        if ($request->hasFile($inputName)) {
            // Delete the old file if it exists
            if ($oldPath && Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }

            // Store the new file
            $image = $request->{$inputName};
            $ext = $image->getClientOriginalExtension();
            $imageName = 'media_' . uniqid() . '.' . $ext;

            // Store file in the specified path on the 'public' disk
            $storedPath = $image->storeAs($path, $imageName, 'public');

            return $storedPath; // Return the relative file path
        }

        return null; // Return null if no file is uploaded
    }

    /** Handle Delete File */
    /**
     * @param String $path
     * @return bool
     */
    public function deleteImage(string $path): bool
    {
        // Check if the file exists and delete it
        if (Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->delete($path);
        }

        return false; // Return false if the file does not exist
    }
}
