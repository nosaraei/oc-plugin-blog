<?php

Route::group(['prefix' => 'api/v1.0'], function() {

    Route::group(['prefix' => 'blog'], function() {

        Route::post('posts/search', 'RainLab\Blog\Api\Controllers\PostsApi@search');
        
        Route::get('posts/get/{slug}', 'RainLab\Blog\Api\Controllers\PostsApi@getPostDetails');
        
        Route::post('categories/get/tree', 'RainLab\Blog\Api\Controllers\CategoriesApi@getTree');

    });

});