<?php
namespace App\Http\Controllers\v1;

    use App\Dto\BaseDto;
    use App\Dto\BaseDtoStatusEnum;
    use App\Http\Controllers\Controller;
    use App\Models\Image;
    use Illuminate\Http\Request;
    use Illuminate\Support\Facades\Storage;
    use Illuminate\Support\Facades\Validator;
    use Exception;

class UploadController extends Controller
{
    public function uploadImage(Request $request)
    {
        // اعتبارسنجی فایل ورودی
        $validator = Validator::make($request->all(), [
            'upload' => 'required|image|max:2048', // فایل باید تصویر باشد
        ]);

        if ($validator->fails()) {
            return response()->json(['uploaded' => false, 'error' => $validator->errors()], 400);
        }

        try {
            // ساخت یک نام فایل یکتا
            $filename = uniqid() . '.' . $request->file('upload')->getClientOriginalExtension();

            // ذخیره فایل در پوشه public/images
            $path = $request->file('upload')->storeAs('images', $filename, 'public');

            // آدرس کامل تصویر برای دسترسی در وب
            $url = Storage::url($path);

            // ذخیره اطلاعات تصویر در دیتابیس
            Image::query()->create([
                'path' => $url,
                'type' => 'article', // یا 'avatar' بر اساس نیاز
            ]);

            // پاسخ مناسب CKEditor
            return response()->json([
                'uploaded' => true,
                'url' => $url
            ]);
        } catch (Exception $e) {
            return response()->json(['uploaded' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function index()
    {
        // این متد می‌تواند برای نمایش فرم آپلود استفاده شود
        // return view('upload');
    }
}
