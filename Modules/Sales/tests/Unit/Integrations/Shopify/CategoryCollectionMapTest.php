<?php

namespace Modules\Sales\Tests\Unit\Integrations\Shopify;

use Modules\Sales\Integrations\Shopify\CategoryCollectionMap;
use Modules\Sales\Tests\TestCase;

class CategoryCollectionMapTest extends TestCase
{
    public function test_it_reads_handles_by_category_id_from_config(): void
    {
        config([
            'sales.shopify.category_collections' => [
                1 => 'bath',
                2 => 'shower',
            ],
        ]);

        $map = new CategoryCollectionMap;

        $this->assertSame([
            1 => 'bath',
            2 => 'shower',
        ], $map->handlesByCategoryId());
        $this->assertSame('bath', $map->handleForCategory(1));
        $this->assertNull($map->handleForCategory(99));
    }
}
