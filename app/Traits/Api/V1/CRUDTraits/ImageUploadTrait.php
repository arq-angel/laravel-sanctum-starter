<?php

namespace App\Traits\Api\V1\CRUDTraits;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

trait ImageUploadTrait
{
    /**
     * @param Request $request
     * @param $inputName
     * @param $path
     * @return string|void
     */
    public function uploadImage(Request $request, $inputName, $path)
    {
        if ($request->hasFile($inputName)) {
            $image = $request->{$inputName};
            $ext = $image->getClientOriginalExtension();
            $imageName = 'media_' . uniqid() . '.' . $ext;

            $image->move(public_path($path), $imageName);

            return $path . '/' . $imageName;
        }
    }

    /**
     * @param Request $request
     * @param $inputName
     * @param $path
     * @param $oldPath
     * @return string|void
     */
    public function updateImage(Request $request, $inputName, $path, $oldPath=null)
    {
        if ($request->hasFile($inputName)) {
            if (File::exists(public_path($oldPath))) {
                File::delete(public_path($oldPath));
            }

            $image = $request->{$inputName};
            $ext = $image->getClientOriginalExtension();
            $imageName = 'media_' . uniqid() . '.' . $ext;

            $image->move(public_path($path), $imageName);

            return $path . '/' . $imageName;
        }
    }

    /** Handle Delete File */
    /**
     * @param String $path
     * @return void
     */
    public function deleteImage(String $path)
    {
        if (File::exists(public_path($path))) {
            File::delete(public_path($path));
        }
    }
}
