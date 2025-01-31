<?php namespace RainLab\Blog\Api\Controllers;

use Illuminate\Http\Request;
use RainLab\Blog\Api\Resources\Post;
use System\Classes\ApiController as Controller;
use DB;
use System\Helpers\CoreUtils;

class PostsApi extends Controller
{
    /**
     * @api {post} /blog/posts/search Posts Search Pro
     * @apiName search
     * @apiGroup Blog
     *
     * @apiParam {Array} ids لیست شناسه های پست ها
     * @apiParam {Array} category_ids لیست شناسه های دسته بندی ها
     * @apiParam {Array} tag_ids لیست شناسه های تگ ها
     * @apiParam {Array} slugs لیست اسلاگ ها
     * @apiParam {String} search_key عبارت مورد جستجو
     * @apiParam {Array} custom_fields فیلد های مورد نیاز در رسپانس، اگر خالی ارسال شود تمام فیلد ها بر می گردد
     *
     * @apiParamExample {json} Request-Example:
     * {
     *   "custom_fields": ["id","title"],
     *   "search_key": "خوب",
     *   "ids": [1,2],
     *   "slugs": ["مقاله-خوب"],
     *   "category_ids": [1,2],
     *   "tag_ids": [1,2],
     * }
     *
     * @apiSuccessExample Success-Response:
     * HTTP/1.1 200 OK
     * {
     *   "ok": true,
     *   "message": "",
     *   "dev_message": "",
     *   "result": {
     *       "posts": ["#api-Models-Post"],
     *       "pagination": "#api-Models-Pagination"
     *   }
     * }
     */
    public function search(Request $request)
    {
        $custom_fields = CoreUtils::collect($request->custom_fields);
        
        $posts = Post
            ::isPublished()
            ->basicFilter($request);

        if($request->category_ids)
            $posts->categories(CoreUtils::collect($request->category_ids));
    
        if($request->tag_ids)
            $posts->filterTags(CoreUtils::collect($request->tag_ids));
        
        if($request->ids)
            $posts->whereIn("id", CoreUtils::collect($request->ids));
    
        if($request->slugs)
            $posts->whereIn("slug", CoreUtils::collect($request->slugs));
        
        if($custom_fields->count() == 0 || $custom_fields->search('categories')){
            $posts->with("categories:id,name,slug");
        }
    
        if($custom_fields->count() == 0 || $custom_fields->search('tags')){
            $posts->with("tags");
        }
    
        if($custom_fields->count() == 0 || $custom_fields->search('featured_images')){
            $posts->with("featured_images");
        }
    
        if($custom_fields->count() == 0 || $custom_fields->search('content_html')){
            $posts->update(['views' => DB::raw('views + 1')]);
        }
    
        $posts->orderBy("published_at", "DESC");
    
        return $this->success($this->pagination($request, $posts, "posts"));
    }

}
