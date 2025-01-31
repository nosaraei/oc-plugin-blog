<?php namespace RainLab\Blog\Api\Controllers;

use Illuminate\Http\Request;
use RainLab\Blog\Api\Resources\Category;
use System\Classes\ApiController as Controller;

class CategoriesApi extends Controller
{
    /**
     * @api {post} /blog/categories/get/tree Get Categories Tree
     * @apiName getTree
     * @apiGroup Blog
     *
     * @apiParam {Number} parent_id شناسه والد دسته بندی
     * @apiParam {Number} depth عمق درخت
     *
     * @apiParamExample {json} Request-Example:
     * {
     *   "parent_id": null,
     *   "depth": 3
     * }
     *
     * @apiSuccessExample Success-Response:
     * HTTP/1.1 200 OK
     * {
     *   "ok": true,
     *   "message": "",
     *   "dev_message": "",
     *   "result": {
     *       "parent_category": "#api-Models-Category"
     *   }
     * }
     */
    public function getTree(Request $request){
        
        return $this->success([
            "parent_category" => Category::getTree([
                "id" => $request->parent_id,
                "name"=> "",
                "slug"=> "",
            ], $request->depth?:0)
        ]);

    }

}
