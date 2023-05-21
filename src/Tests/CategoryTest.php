<?php

namespace Kiralyta\Ntak\Tests;

use Kiralyta\Ntak\Enums\NTAKCategory;
use Kiralyta\Ntak\Enums\NTAKSubcategory;
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
            NTAKCategory::values(),
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
            $randomCategory->subcategories(),
            NTAK::subcategories($randomCategory)
        );

        $randomSubcategory = collect($randomCategory->subcategories())
            ->random();

        $this->assertSame(
            true,
            collect(NTAK::subcategories($randomCategory))->contains(
                fn (NTAKSubcategory $subcategory) =>
                $subcategory === $randomSubcategory
            )
        );
    }

    /**
     * randomCategory
     *
     * @return NTAKCategory
     */
    protected function randomCategory(): NTAKCategory
    {
        return NTAKCategory::random();
    }
}
