<?php

namespace App\Http\Controllers\v1;

use App\Dto\BaseDtoStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Image;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Dto\BaseDto;
use Exception;


class UploadController extends Controller
{
    public function index()
    {
    }

    public function store(Request $request)
    {
        Log::info('Starting the image upload process.');

        $validator = Validator::make($request->all(), [
            'image' => 'required|image|max:2048',
        ]);

        if ($validator->fails()) {
            Log::error('Validation failed:', $validator->errors()->toArray());
            return response()->json([
                'error' => $validator->errors(),
                'status' => 'error',
            ], 400);
        }

        try {
            $nameFile = time() . '.' . $request->image->extension();
            $path = $request->file('image')->store('images', 'public');

            Log::info('Image stored successfully at path: ' . $path);

            $main_path_file = asset('images/' . $nameFile);

            return response()->json([
                'data' => [
                    'image' => $nameFile,
                    'url' => $main_path_file,
                    'status' => 'success',
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Error while uploading image: ' . $e->getMessage());
            return response()->json([
                'message' => 'خطا در بارگذاری تصویر.',
                'error' => $e->getMessage(),
                'status' => 'error',
            ], 500);
        }
    }

}
