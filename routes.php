<?php

Route::group(['prefix' => 'api/v1.0'], function() {

    Route::group(['prefix' => 'blog'], function() {

        Route::post('posts/search', 'RainLab\Blog\Api\Controllers\PostsApi@search');
        
        Route::post('categories/get/tree', 'RainLab\Blog\Api\Controllers\CategoriesApi@getTree');

    });

});