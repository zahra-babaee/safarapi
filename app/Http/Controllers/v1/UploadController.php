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
        $validator = Validator::make($request->all(), [
            'upload' => 'required|image|max:2048', // اعتبارسنجی فایل
            'type' => 'required|string', // نوع تصویر باید ارسال شود
            'article_id' => 'nullable|exists:articles,id', // اطمینان از وجود مقاله
            'user_id' => 'nullable|exists:users,id', // اطمینان از وجود کاربر
        ]);

        if ($validator->fails()) {
            return response()->json(['uploaded' => false, 'error' => $validator->errors()], 400);
        }

        try {
            $filename = uniqid() . '.' . $request->file('upload')->getClientOriginalExtension();
            $path = $request->file('upload')->storeAs('images', $filename, 'public');

            $url = Storage::url($path);

            Image::query()->create([
                'path' => $url,
                'type' => $request->input('type'),
                'user_id' => $request->input('user_id') ?? null,
                'article_id' => $request->input('article_id') ?? null,
            ]);

            return response()->json([
                'uploaded' => true,
                'url' => $url
            ]);
        } catch (Exception $e) {
            return response()->json(['uploaded' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
