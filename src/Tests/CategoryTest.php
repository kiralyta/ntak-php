<?php

namespace Kiralyta\Ntak\Tests;

use Kiralyta\Ntak\Enums\Category;
use Kiralyta\Ntak\Enums\SubCategory;
use Kiralyta\Ntak\NTAK;
use PHPUnit\Framework\TestCase;

class CategoryTest extends TestCase
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

        $this->assertSame(
            $randomCategory->subCategories(),
            NTAK::subCategories($randomCategory)
        );

        $randomSubCategory = collect($randomCategory->subCategories())
            ->random();

        $this->assertSame(
            true,
            collect(NTAK::subCategories($randomCategory))->contains(
                fn (SubCategory $subCategory) =>
                $subCategory === $randomSubCategory
            )
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
