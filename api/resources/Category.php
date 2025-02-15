<?php namespace RainLab\Blog\Api\Resources;

use System\Traits\Resource;

/**
 * @api {دسته بندی وبلاگ} / Category
 * @apiName Category
 * @apiGroup Models
 *
 * @apiSuccessExample Category Fields
 * {
 *   "id": 1,
 *   "name": "دسته بندی اول",
 *   "slug": "دسته-بندی-اول",
 *   "direct_children_count": 5,
 *   "children": ["#api-Models-Category"]
 * }
 */

class Category extends \RainLab\Blog\Models\Category
{
    use Resource;
    
    protected $hidden = [
        "code",
        "description",
        "created_at",
        "updated_at",
        "nest_depth",
        "nest_left",
        "nest_right",
        "parent_id",
        "translations"
    ];
    
    protected $appends = [
        //"direct_children_count"
    ];
    
}