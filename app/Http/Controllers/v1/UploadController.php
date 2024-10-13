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
            'upload' => 'required|image|max:2048', // تغییرات در اعتبارسنجی
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->toArray();

            return response()->json(new BaseDto(
                BaseDtoStatusEnum::ERROR,
                'خطاهای اعتبارسنجی رخ داده است.',
                $errors
            ), 400);
        }

        try {
            // ساخت یک نام فایل یکتا
            $filename = uniqid() . '.' . $request->file('upload')->getClientOriginalExtension();

            // ذخیره فایل در پوشه‌ی public/images
            $path = $request->file('upload')->storeAs('images', $filename, 'public');

            // آدرس کامل تصویر برای دسترسی در وب
            $url = Storage::url($path);

            // ذخیره تصویر در دیتابیس (در صورت نیاز)
            Image::query()->create([
                'path' => $url,
                'type' => 'article', // یا 'avatar' بر اساس نیاز
            ]);

            return response()->json(new BaseDto(
                BaseDtoStatusEnum::OK,
                'تصویر با موفقیت بارگذاری شد.',
                data: ['url' => $url]
            ), 201);
        } catch (Exception $e) {
            return response()->json(new BaseDto(
                BaseDtoStatusEnum::ERROR,
                'خطا در بارگذاری تصویر.',
                data: [
                    'error' => $e->getMessage(),]
            ), 500);
        }
    }


    public function index()
    {
        // این متد می‌تواند برای نمایش فرم آپلود استفاده شود
        // return view('upload');
    }
}
//    public function store(Request $request)
//    {
//        // اعتبارسنجی فایل ورودی
//        $validator = Validator::make($request->all(), [
//            'image' => 'required|image|max:2048', // تغییرات در اعتبارسنجی
//        ]);
//
//        if ($validator->fails()) {
//            return response()->json([
//                'error' => $validator->errors(),
//                'status' => 'error',
//            ], 400);
//        }
//
//        try {
//            // ساخت یک نام فایل یکتا
//            $filename = uniqid() . '.' . $request->file('image')->getClientOriginalExtension();
//
//            // ذخیره فایل در پوشه‌ی public/profile_photos
//            $path = $request->file('image')->storeAs('profile_photos', $filename, 'public');
//
//            // آدرس کامل تصویر برای دسترسی در وب
//            $main_path_file = Storage::url($path);
//
//            return response()->json([
//                'data' => [
//                    'image' => $filename,
//                    'url' => $main_path_file,
//                    'status' => 'success',
//                ],
//            ]);
//        } catch (\Exception $e) {
//            return response()->json([
//                'message' => 'خطا در بارگذاری تصویر.',
//                'error' => $e->getMessage(),
//                'status' => 'error',
//            ], 500);
//        }
//    }

