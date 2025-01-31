<?php namespace RainLab\Blog\Api\Resources;

use System\Traits\Resource;
use System\Helpers\DateTime;

/**
 * @api {پست وبلاگ} / Post
 * @apiName Post
 * @apiGroup Models
 *
 * @apiSuccessExample Post Fields
 * {
 *   "id": 1,
 *   "title": "مقاله خوب",
 *   "slug": "مقاله-خوب",
 *   "views": 4,
 *   "excerpt": "خلاصه ی مقاله خوب",
 *   "content_html": "<p>مقاله خوب</p>",
 *   "published_at": "1397/10/15 08:55:00",
 *   "featured_images": ["#api-Models-FileSystem"],
 *   "categories": ["#api-Models-Category"],
 *   "tags": [
 *       {
 *           "id": 1,
 *           "name": "فرهنگی"
 *       }
 *   ]
 * }
 */

class Post extends \RainLab\Blog\Models\Post
{
    use Resource;
    
    protected $hidden = [
        "user_id",
        "content",
        "published",
        "created_at",
        "updated_at",
        "metadata",
    ];
    
    protected $appends = [];
    
    public function getPublishedAtAttribute()
    {
        return DateTime::output($this->attributes["published_at"]);
    }
}