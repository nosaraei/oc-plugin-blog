<?php namespace RainLab\Blog\Models;

use Url;
use Html;
use Lang;
use Model;
use Config;
use Markdown;
use BackendAuth;
use Carbon\Carbon;
use Backend\Models\User as BackendUser;
use Cms\Classes\Page as CmsPage;
use Cms\Classes\Theme;
use Cms\Classes\Controller;
use October\Rain\Database\NestedTreeScope;
use RainLab\Blog\Classes\TagProcessor;
use RainLab\Blog\Models\Settings as BlogSettings;
use ValidationException;

/**
 * Class Post
 */
class Post extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'rainlab_blog_posts';
    public $implement = ['@RainLab.Translate.Behaviors.TranslatableModel'];

    /*
     * Validation
     */
    public $rules = [
        'title'   => 'required',
        'slug'    => ['required', 'regex:/^[a-zالف-ی0-9\/\:_\-\*\[\]\+\?\|]*$/i', 'unique:rainlab_blog_posts'],
        'content' => 'required',
        'excerpt' => '',
        "cover" => "max:1024"
    ];

    /**
     * @var array Attributes that support translation, if available.
     */
    public $translatable = [
        'title',
        'content',
        'content_html',
        'excerpt',
        'metadata',
        ['slug', 'index' => true]
    ];

    /**
     * @var array Attributes to be stored as JSON
     */
    protected $jsonable = ['metadata'];

    /**
     * The attributes that should be mutated to dates.
     * @var array
     */
    protected $dates = ['published_at'];

    /**
     * The attributes on which the post list can be ordered.
     * @var array
     */
    public static $allowedSortingOptions = [
        'title asc'         => 'rainlab.blog::lang.sorting.title_asc',
        'title desc'        => 'rainlab.blog::lang.sorting.title_desc',
        'created_at asc'    => 'rainlab.blog::lang.sorting.created_asc',
        'created_at desc'   => 'rainlab.blog::lang.sorting.created_desc',
        'updated_at asc'    => 'rainlab.blog::lang.sorting.updated_asc',
        'updated_at desc'   => 'rainlab.blog::lang.sorting.updated_desc',
        'published_at asc'  => 'rainlab.blog::lang.sorting.published_asc',
        'published_at desc' => 'rainlab.blog::lang.sorting.published_desc',
        'random'            => 'rainlab.blog::lang.sorting.random'
    ];

    /*
     * Relations
     */
    public $belongsTo = [
        'user' => BackendUser::class
    ];

    public $belongsToMany = [
        'categories' => [
            Category::class,
            'table' => 'rainlab_blog_posts_categories',
            'order' => 'name'
        ],
        'tags' => [
            'RainLab\Blog\Models\Tag',
            'table' => 'rainlab_blog_posts_tags',
            'order' => 'name'
        ]
    ];
    
    public $attachOne = [
        'cover' => [\System\Models\File::class],
    ];
    
    public $attachMany = [
        'featured_images' => [\System\Models\File::class, 'order' => 'sort_order'],
        'content_images'  => \System\Models\File::class
    ];

    /**
     * @var array The accessors to append to the model's array form.
     */
    protected $appends = ['summary', 'has_summary'];

    public $preview = null;

    /**
     * Limit visibility of the published-button
     *
     * @param       $fields
     * @param  null $context
     * @return void
     */
    public function filterFields($fields, $context = null)
    {
        if (!isset($fields->published, $fields->published_at)) {
            return;
        }

        $user = BackendAuth::getUser();

        if (!$user->hasAnyAccess(['rainlab.blog.access_publish'])) {
            $fields->published->hidden = true;
            $fields->published_at->hidden = true;
        }
        else {
            $fields->published->hidden = false;
            $fields->published_at->hidden = false;
        }
    }

    /**
     * beforeValidate
     */
    public function beforeValidate()
    {
        if (empty($this->user)) {
            $user = BackendAuth::getUser();
            if (!is_null($user)) {
                $this->user = $user->id;
            }
        }

        $this->content_html = self::formatHtml($this->content);
    }

    /**
     * afterValidate
     */
    public function afterValidate()
    {
        if ($this->published && !$this->published_at) {
            throw new ValidationException([
               'published_at' => Lang::get('rainlab.blog::lang.post.published_validation')
            ]);
        }
    }

    /**
     * getUserOptions
     */
    public function getUserOptions()
    {
        $options = [];

        foreach (BackendUser::all() as $user) {
            $options[$user->id] = $user->fullname . ' ('.$user->login.')';
        }

        return $options;
    }

    /**
     * Sets the "url" attribute with a URL to this object.
     * @param string $pageName
     * @param Controller $controller
     * @param array $params Override request URL parameters
     *
     * @return string
     */
    public function setUrl($pageName, $controller, $params = [])
    {
        $params = array_merge([
            'id'   => $this->id,
            'slug' => $this->slug,
        ], $params);

        if (empty($params['category'])) {
            $params['category'] = $this->categories->count() ? $this->categories->first()->slug : null;
        }

        // Expose published year, month and day as URL parameters.
        if ($this->published) {
            $params['year']  = $this->published_at->format('Y');
            $params['month'] = $this->published_at->format('m');
            $params['day']   = $this->published_at->format('d');
        }

        return $this->url = $controller->pageUrl($pageName, $params);
    }

    /**
     * Used to test if a certain user has permission to edit post,
     * returns TRUE if the user is the owner or has other posts access.
     * @param  BackendUser $user
     * @return bool
     */
    public function canEdit(BackendUser $user)
    {
        return ($this->user_id == $user->id) || $user->hasAnyAccess(['rainlab.blog.access_other_posts']);
    }

    /**
     * formatHtml for the post
     */
    public static function formatHtml($input, $preview = false)
    {
        if (BlogSettings::get('force_richeditor_editor', false)) {
            $result = trim($input);
        }
        else {
            $result = Markdown::parse(trim($input));
        }

        // Check to see if the HTML should be cleaned from potential XSS
        $user = BackendAuth::getUser();
        if (!$user || !$user->hasAccess('backend.allow_unsafe_markdown')) {
            $result = Html::clean($result);
        }

        if ($preview) {
            $result = str_replace('<pre>', '<pre class="prettyprint">', $result);
        }

        $result = TagProcessor::instance()->processTags($result, $preview);

        return $result;
    }

    //
    // Scopes
    //

    public function scopeIsPublished($query)
    {
        return $query
            ->whereNotNull('published')
            ->where('published', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<', Carbon::now())
        ;
    }
    
    public function scopeFilterTags($query, $tags)
    {
        return $query->whereHas('tags', function($q) use ($tags) {
            $q->whereIn('id', $tags);
        });
    }

    /**
     * Lists posts for the frontend
     *
     * @param        $query
     * @param  array $options Display options
     * @return Post
     */
    public function scopeListFrontEnd($query, $options)
    {
        /*
         * Default options
         */
        extract(array_merge([
            'page'             => 1,
            'perPage'          => 30,
            'sort'             => 'created_at',
            'categories'       => null,
            'exceptCategories' => null,
            'category'         => null,
            'search'           => '',
            'published'        => true,
            'exceptPost'       => null
        ], $options));

        $searchableFields = ['title', 'slug', 'excerpt', 'content'];

        if ($published) {
            $query->isPublished();
        }

        /*
         * Except post(s)
         */
        if ($exceptPost) {
            $exceptPosts = (is_array($exceptPost)) ? $exceptPost : [$exceptPost];
            $exceptPostIds = [];
            $exceptPostSlugs = [];

            foreach ($exceptPosts as $exceptPost) {
                $exceptPost = trim($exceptPost);

                if (is_numeric($exceptPost)) {
                    $exceptPostIds[] = $exceptPost;
                } else {
                    $exceptPostSlugs[] = $exceptPost;
                }
            }

            if (count($exceptPostIds)) {
                $query->whereNotIn('id', $exceptPostIds);
            }
            if (count($exceptPostSlugs)) {
                $query->whereNotIn('slug', $exceptPostSlugs);
            }
        }

        /*
         * Sorting
         */
        if (in_array($sort, array_keys(static::$allowedSortingOptions))) {
            if ($sort == 'random') {
                $query->inRandomOrder();
            } else {
                @list($sortField, $sortDirection) = explode(' ', $sort);

                if (is_null($sortDirection)) {
                    $sortDirection = "desc";
                }

                $query->orderBy($sortField, $sortDirection);
            }
        }

        /*
         * Search
         */
        $search = trim($search);
        if (strlen($search)) {
            $query->searchWhere($search, $searchableFields);
        }

        /*
         * Categories
         */
        if ($categories !== null) {
            $categories = is_array($categories) ? $categories : [$categories];
            $query->whereHas('categories', function($q) use ($categories) {
                $q->withoutGlobalScope(NestedTreeScope::class)->whereIn('id', $categories);
            });
        }

        /*
         * Except Categories
         */
        if (!empty($exceptCategories)) {
            $exceptCategories = is_array($exceptCategories) ? $exceptCategories : [$exceptCategories];
            array_walk($exceptCategories, 'trim');

            $query->whereDoesntHave('categories', function ($q) use ($exceptCategories) {
                $q->withoutGlobalScope(NestedTreeScope::class)->whereIn('slug', $exceptCategories);
            });
        }

        /*
         * Category, including children
         */
        if ($category !== null) {
            $category = Category::find($category);

            $categories = $category->getAllChildrenAndSelf()->lists('id');
            $query->whereHas('categories', function($q) use ($categories) {
                $q->withoutGlobalScope(NestedTreeScope::class)->whereIn('id', $categories);
            });
        }

        return $query->paginate($perPage, $page);
    }

    /**
     * Allows filtering for specifc categories.
     * @param  Illuminate\Query\Builder  $query      QueryBuilder
     * @param  array                     $categories List of category ids
     * @return Illuminate\Query\Builder              QueryBuilder
     */
    public function scopeCategories($query, $categories)
    {
        return $query->whereHas('categories', function($q) use ($categories) {
            $q->withoutGlobalScope(NestedTreeScope::class)->whereIn('id', $categories);
        });
    }

    //
    // Summary / Excerpt
    //

    /**
     * Used by "has_summary", returns true if this post uses a summary (more tag).
     * @return boolean
     */
    public function getHasSummaryAttribute()
    {
        $more = Config::get('rainlab.blog::summary_separator', '<!-- more -->');
        $length = Config::get('rainlab.blog::summary_default_length', 600);

        return (
            !!strlen(trim($this->excerpt)) ||
            strpos($this->content_html, $more) !== false ||
            strlen(Html::strip($this->content_html)) > $length
        );
    }

    /**
     * Used by "summary", if no excerpt is provided, generate one from the content.
     * Returns the HTML content before the <!-- more --> tag or a limited 600
     * character version.
     *
     * @return string
     */
    public function getSummaryAttribute()
    {
        $excerpt = $this->excerpt;
        if (strlen(trim($excerpt))) {
            return $excerpt;
        }

        $more = Config::get('rainlab.blog::summary_separator', '<!-- more -->');

        if (strpos($this->content_html, $more) !== false) {
            $parts = explode($more, $this->content_html);

            return array_get($parts, 0);
        }

        $length = Config::get('rainlab.blog::summary_default_length', 600);

        return Html::limit($this->content_html, $length);
    }

    //
    // Next / Previous
    //

    /**
     * Apply a constraint to the query to find the nearest sibling
     *
     *     // Get the next post
     *     Post::applySibling()->first();
     *
     *     // Get the previous post
     *     Post::applySibling(-1)->first();
     *
     *     // Get the previous post, ordered by the ID attribute instead
     *     Post::applySibling(['direction' => -1, 'attribute' => 'id'])->first();
     *
     * @param       $query
     * @param array $options
     * @return
     */
    public function scopeApplySibling($query, $options = [])
    {
        if (!is_array($options)) {
            $options = ['direction' => $options];
        }

        extract(array_merge([
            'direction' => 'next',
            'attribute' => 'published_at'
        ], $options));

        $isPrevious = in_array($direction, ['previous', -1]);
        $directionOrder = $isPrevious ? 'asc' : 'desc';
        $directionOperator = $isPrevious ? '>' : '<';

        $query->where('id', '<>', $this->id);

        if (!is_null($this->$attribute)) {
            $query->where($attribute, $directionOperator, $this->$attribute);
        }

        return $query->orderBy($attribute, $directionOrder);
    }

    /**
     * Returns the next post, if available.
     * @return self
     */
    public function nextPost()
    {
        return self::isPublished()->applySibling()->first();
    }

    /**
     * Returns the previous post, if available.
     * @return self
     */
    public function previousPost()
    {
        return self::isPublished()->applySibling(-1)->first();
    }

    //
    // Menu helpers
    //

    /**
     * Handler for the pages.menuitem.getTypeInfo event.
     * Returns a menu item type information. The type information is returned as array
     * with the following elements:
     * - references - a list of the item type reference options. The options are returned in the
     *   ["key"] => "title" format for options that don't have sub-options, and in the format
     *   ["key"] => ["title"=>"Option title", "items"=>[...]] for options that have sub-options. Optional,
     *   required only if the menu item type requires references.
     * - nesting - Boolean value indicating whether the item type supports nested items. Optional,
     *   false if omitted.
     * - dynamicItems - Boolean value indicating whether the item type could generate new menu items.
     *   Optional, false if omitted.
     * - cmsPages - a list of CMS pages (objects of the Cms\Classes\Page class), if the item type requires a CMS page reference to
     *   resolve the item URL.
     *
     * @param string $type Specifies the menu item type
     * @return array Returns an array
     */
    public static function getMenuTypeInfo($type)
    {
        $result = [];

        if ($type == 'blog-post') {
            $references = [];

            $posts = self::orderBy('title')->get();
            foreach ($posts as $post) {
                $references[$post->id] = $post->title;
            }

            $result = [
                'references'   => $references,
                'nesting'      => false,
                'dynamicItems' => false
            ];
        }

        if ($type == 'all-blog-posts') {
            $result = [
                'dynamicItems' => true
            ];
        }

        if ($type == 'category-blog-posts') {
            $references = [];

            $categories = Category::orderBy('name')->get();
            foreach ($categories as $category) {
                $references[$category->id] = $category->name;
            }

            $result = [
                'references'   => $references,
                'dynamicItems' => true
            ];
        }

        if ($result) {
            $theme = Theme::getActiveTheme();

            $pages = CmsPage::listInTheme($theme, true);
            $cmsPages = [];

            foreach ($pages as $page) {
                if (!$page->hasComponent('blogPost')) {
                    continue;
                }

                /*
                 * Component must use a categoryPage filter with a routing parameter and post slug
                 * eg: categoryPage = "{{ :somevalue }}", slug = "{{ :somevalue }}"
                 */
                $properties = $page->getComponentProperties('blogPost');
                if (!isset($properties['categoryPage']) || !preg_match('/{{\s*:/', $properties['slug'])) {
                    continue;
                }

                $cmsPages[] = $page;
            }

            $result['cmsPages'] = $cmsPages;
        }

        return $result;
    }

    /**
     * Handler for the pages.menuitem.resolveItem event.
     * Returns information about a menu item. The result is an array
     * with the following keys:
     * - url - the menu item URL. Not required for menu item types that return all available records.
     *   The URL should be returned relative to the website root and include the subdirectory, if any.
     *   Use the Url::to() helper to generate the URLs.
     * - isActive - determines whether the menu item is active. Not required for menu item types that
     *   return all available records.
     * - items - an array of arrays with the same keys (url, isActive, items) + the title key.
     *   The items array should be added only if the $item's $nesting property value is TRUE.
     *
     * @param \RainLab\Pages\Classes\MenuItem $item Specifies the menu item.
     * @param \Cms\Classes\Theme $theme Specifies the current theme.
     * @param string $url Specifies the current page URL, normalized, in lower case
     * The URL is specified relative to the website root, it includes the subdirectory name, if any.
     * @return mixed Returns an array. Returns null if the item cannot be resolved.
     */
    public static function resolveMenuItem($item, $url, $theme)
    {
        $result = null;

        if ($item->type == 'blog-post') {
            if (!$item->reference || !$item->cmsPage) {
                return;
            }

            $category = self::find($item->reference);
            if (!$category) {
                return;
            }

            $pageUrl = self::getPostPageUrl($item->cmsPage, $category, $theme);
            if (!$pageUrl) {
                return;
            }

            $pageUrl = Url::to($pageUrl);

            $result = [];
            $result['url'] = $pageUrl;
            $result['isActive'] = $pageUrl == $url;
            $result['mtime'] = $category->updated_at;
        }
        elseif ($item->type == 'all-blog-posts') {
            $result = [
                'items' => []
            ];

            $posts = self::isPublished()
                ->orderBy('title')
                ->get()
            ;

            foreach ($posts as $post) {
                $postItem = [
                    'title' => $post->title,
                    'url'   => self::getPostPageUrl($item->cmsPage, $post, $theme),
                    'mtime' => $post->updated_at
                ];

                $postItem['isActive'] = $postItem['url'] == $url;

                $result['items'][] = $postItem;
            }
        }
        elseif ($item->type == 'category-blog-posts') {
            if (!$item->reference || !$item->cmsPage) {
                return;
            }

            $category = Category::find($item->reference);
            if (!$category) {
                return;
            }

            $result = [
                'items' => []
            ];

            $query = self::isPublished()
            ->orderBy('title');

            $categories = $category->getAllChildrenAndSelf()->lists('id');
            $query->whereHas('categories', function($q) use ($categories) {
                $q->withoutGlobalScope(NestedTreeScope::class)->whereIn('id', $categories);
            });

            $posts = $query->get();

            foreach ($posts as $post) {
                $postItem = [
                    'title' => $post->title,
                    'url'   => self::getPostPageUrl($item->cmsPage, $post, $theme),
                    'mtime' => $post->updated_at
                ];

                $postItem['isActive'] = $postItem['url'] == $url;

                $result['items'][] = $postItem;
            }
        }

        return $result;
    }

    /**
     * Returns URL of a post page.
     *
     * @param $pageCode
     * @param $category
     * @param $theme
     */
    protected static function getPostPageUrl($pageCode, $category, $theme)
    {
        $page = CmsPage::loadCached($theme, $pageCode);
        if (!$page) {
            return;
        }

        $properties = $page->getComponentProperties('blogPost');
        if (!isset($properties['slug'])) {
            return;
        }

        /*
         * Extract the routing parameter name from the category filter
         * eg: {{ :someRouteParam }}
         */
        if (!preg_match('/^\{\{([^\}]+)\}\}$/', $properties['slug'], $matches)) {
            return;
        }

        $paramName = substr(trim($matches[1]), 1);
        $params = [
            $paramName => $category->slug,
            'year'  => $category->published_at ? $category->published_at->format('Y') : '',
            'month' => $category->published_at ? $category->published_at->format('m') : '',
            'day'   => $category->published_at ? $category->published_at->format('d') : ''
        ];
        $url = CmsPage::url($page->getBaseFileName(), $params);

        return $url;
    }
}
