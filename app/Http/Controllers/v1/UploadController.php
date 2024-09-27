<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class UploadController extends Controller
{
    public function index()
    {
        // این متد می‌تواند برای نمایش فرم آپلود استفاده شود
        // return view('upload');
    }

    public function store(Request $request)
    {
        // اعتبارسنجی فایل ورودی
        $validator = Validator::make($request->all(), [
            'image' => 'required|image|max:2048', // تغییرات در اعتبارسنجی
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => $validator->errors(),
                'status' => 'error',
            ], 400);
        }

        try {
            // ساخت یک نام فایل یکتا
            $filename = uniqid() . '.' . $request->file('image')->getClientOriginalExtension();

            // ذخیره فایل در پوشه‌ی public/profile_photos
            $path = $request->file('image')->storeAs('profile_photos', $filename, 'public');

            // آدرس کامل تصویر برای دسترسی در وب
            $main_path_file = Storage::url($path);

            return response()->json([
                'data' => [
                    'image' => $filename,
                    'url' => $main_path_file,
                    'status' => 'success',
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'خطا در بارگذاری تصویر.',
                'error' => $e->getMessage(),
                'status' => 'error',
            ], 500);
        }
    }
}
