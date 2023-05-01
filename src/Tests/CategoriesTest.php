<?php

namespace Kiralyta\Ntak\Tests;

use Kiralyta\Ntak\Enums\Category;
use Kiralyta\Ntak\NTAK;
use PHPUnit\Framework\TestCase;

class CategoriesTest extends TestCase
{
    /**
     * test_list_categories
     *
     * @return void
     */
    public function test_list_categories(): void
    {
        $this->assertEquals(
            Category::values(),
            NTAK::categories()
        );
    }

    /**
     * test_list_sub_categories
     *
     * @return void
     */
    public function test_list_sub_categories(): void
    {
        $randomCategory = $this->randomCategory();

        dump($randomCategory);

        $this->assertSame(
            $randomCategory->subCategories(),
            NTAK::subCategories($randomCategory)
        );
    }

    /**
     * randomCategory
     *
     * @return Category
     */
    protected function randomCategory(): Category
    {
        return Category::random();
    }
}
