<?php

namespace Kiralyta\Ntak\Tests;

use Kiralyta\Ntak\Enums\NTAKCategory;
use Kiralyta\Ntak\Enums\NTAKSubCategory;
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
            $randomCategory->subCategories(),
            NTAK::subCategories($randomCategory)
        );

        $randomSubCategory = collect($randomCategory->subCategories())
            ->random();

        $this->assertSame(
            true,
            collect(NTAK::subCategories($randomCategory))->contains(
                fn (NTAKSubCategory $subCategory) =>
                $subCategory === $randomSubCategory
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
