<?php

namespace App\Http\Controllers\v1;

use App\Dto\BaseDto;
use App\Dto\BaseDtoStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ArticleController extends Controller
{
    /**
     * @throws ValidationException
     */
    public function store(Request $request)
    {
        // اعتبارسنجی داده‌ها
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required', // متن مقاله
            'category_id' => 'required|exists:categories,id',
            'has_photo' => 'nullable|boolean',
            'images' => 'array', // افزودن اعتبارسنجی برای آرایه تصاویر
            'images.*' => 'url' // هر آدرس تصویر باید یک URL معتبر باشد
        ], [
            'title.required' => 'عنوان نباید خالی باشد.',
            'title.string' => 'عنوان باید رشته باشد.',
            'title.max' => 'عنوان باید حداکثر 255 کاراکتر باشد.',
            'description.required' => 'متن نباید خالی باشد.',
            'category_id.required' => 'دسته‌بندی نباید خالی باشد.',
            'category_id.exists' => 'دسته‌بندی انتخاب شده معتبر نیست.',
            'has_photo.boolean' => 'مقدار عکس باید به صورت صحیح/غلط باشد.',
            'images.array' => 'تصاویر باید یک آرایه باشند.',
            'images.*.url' => 'هر تصویر باید یک URL معتبر باشد.',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->toArray();

            return response()->json(new BaseDto(
                BaseDtoStatusEnum::ERROR,
                'خطاهای اعتبارسنجی رخ داده است.',
                $errors
            ), 400);
        }

        // دریافت داده‌های اعتبارسنجی‌شده
        $validatedData = $validator->validated();

        // اضافه کردن آدرس تصاویر به متن مقاله
        if (isset($validatedData['images'])) {
            foreach ($validatedData['images'] as $imageUrl) {
                $validatedData['description'] .= '<img src="' . $imageUrl . '" alt="Image">';
            }
        }

        // ذخیره مقاله با وضعیت در انتظار تایید
        $article = Article::query()->create([
            'title' => $validatedData['title'],
            'description' => $validatedData['description'],
            'category_id' => $validatedData['category_id'],
            'user_id' => auth()->id(), // نویسنده مقاله
            'status' => 'pending', // وضعیت در انتظار تایید
            'has_photo' => $validatedData['has_photo'] ?? false,
        ]);

        return response()->json(new BaseDto(
            BaseDtoStatusEnum::OK,
            'مقاله با موفقیت ثبت شد.',
            $article
        ), 201);
    }

    /**
     * @throws ValidationException
     */
    public function update(Request $request, $articleId)
    {
        // اعتبارسنجی داده‌ها
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required',
            'category_id' => 'required|exists:categories,id',
            'has_photo' => 'nullable|boolean',
            'images' => 'array',
            'images.*' => 'url',
        ], [
            'title.required' => 'عنوان نباید خالی باشد.',
            'title.string' => 'عنوان باید رشته باشد.',
            'title.max' => 'عنوان باید حداکثر 255 کاراکتر باشد.',
            'description.required' => 'متن نباید خالی باشد.',
            'category_id.required' => 'دسته‌بندی نباید خالی باشد.',
            'category_id.exists' => 'دسته‌بندی انتخاب شده معتبر نیست.',
            'has_photo.boolean' => 'مقدار عکس باید به صورت صحیح/غلط باشد.',
            'images.array' => 'تصاویر باید یک آرایه باشند.',
            'images.*.url' => 'هر تصویر باید یک URL معتبر باشد.',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->toArray();
            return response()->json(new BaseDto(
                BaseDtoStatusEnum::ERROR,
                'خطاهای اعتبارسنجی رخ داده است.',
                $errors
            ), 400);
        }

        // دریافت داده‌های اعتبارسنجی‌شده
        $validatedData = $validator->validated();

        // اضافه کردن آدرس تصاویر به متن مقاله
        if (isset($validatedData['images'])) {
            $validatedData['description'] = ''; // پاک کردن توضیحات قبلی
            foreach ($validatedData['images'] as $imageUrl) {
                $validatedData['description'] .= '<img src="' . $imageUrl . '" alt="Image">';
            }
        }

        // پیدا کردن مقاله و به‌روزرسانی آن
        $article = Article::query()->findOrFail($articleId);
        $article->update([
            'title' => $validatedData['title'],
            'description' => $validatedData['description'],
            'category_id' => $validatedData['category_id'],
            'has_photo' => $validatedData['has_photo'] ?? false,
        ]);

        return response()->json(new BaseDto(
            BaseDtoStatusEnum::OK,
            'مقاله با موفقیت به‌روزرسانی شد.',
            $article
        ), 200);
    }

    public function destroy($articleId)
    {
        // پیدا کردن مقاله و حذف آن
        $article = Article::query()->findOrFail($articleId);
        $article->delete();

        return response()->json(new BaseDto(
            BaseDtoStatusEnum::OK,
            'مقاله با موفقیت حذف شد.'
        ), 204);
    }

    public function getPublishedArticles()
    {
        $articles = Article::query()->where('status', 'published')->with('category')->get();

        return response()->json(new BaseDto(
            BaseDtoStatusEnum::OK,
            'لیست مقالات منتشر شده با موفقیت بازیابی شد.',
            $articles
        ));
    }

    public function getPendingArticles()
    {
        $userId = auth()->id();

        $articles = Article::query()->where('user_id', $userId)
            ->where('status', 'pending')
            ->with('category')
            ->get();

        return response()->json(new BaseDto(
            BaseDtoStatusEnum::OK,
            'لیست مقالات در حال انتظار تایید با موفقیت بازیابی شد.',
            $articles
        ));
    }
}
