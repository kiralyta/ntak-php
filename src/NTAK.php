<?php

namespace Kiralyta\Ntak;

use Kiralyta\Ntak\Enums\Category;

class NTAK
{
    /**
     * Lists the categories
     *
     * @return array
     */
    public static function categories(): array
    {
        return Category::values();
    }

    /**
     * Lists the subcategories of a category
     *
     * @param  Category $category
     * @return array
     */
    public static function subCategories(Category $category): array
    {
        return $category->subCategories();
    }
}
