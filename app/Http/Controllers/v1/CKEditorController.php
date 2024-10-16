<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class CKEditorController extends Controller
{
    public function upload(Request $request)
    {
        Log::info('Starting CKEditor image upload.');

        // اعتبارسنجی فایل ارسالی
        $validator = Validator::make($request->all(), [
            'upload' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048', // محدودیت فایل و نوع آن
        ]);

        if ($validator->fails()) {
            // ارسال پیام خطا به فرمت مورد قبول CKEditor
            return response()->json([
                'uploaded' => false,
                'error' => [
                    'message' => 'فایل ارسالی معتبر نیست.'
                ]
            ], 400);
        }

        try {
            // ذخیره‌سازی تصویر
            $file = $request->file('upload');
            $path = $file->store('ckeditor_images', 'public');

            // ساخت URL برای دسترسی به تصویر
            $url = asset('storage/' . $path);

            Log::info('CKEditor image uploaded successfully to path: ' . $path);

            // پاسخ به CKEditor با آدرس تصویر آپلود شده
            return response()->json([
                'uploaded' => true,
                'url' => $url
            ]);

        } catch (Exception $e) {
            Log::error('Error while uploading image in CKEditor: ' . $e->getMessage());

            // ارسال پیام خطا به فرمت مورد قبول CKEditor
            return response()->json([
                'uploaded' => false,
                'error' => [
                    'message' => 'خطایی در بارگذاری تصویر رخ داد.'
                ]
            ], 500);
        }
    }
}
