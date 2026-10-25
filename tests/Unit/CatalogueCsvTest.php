<?php

namespace Tests\Unit;

use App\Support\CatalogueCsv;
use App\Support\ProductCsv;
use PHPUnit\Framework\TestCase;

class CatalogueCsvTest extends TestCase
{
    /**
     * A basic unit test example.
     */
    public function test_preserves_quoted_columns_and_client_skus(): void
    {
        $csv = "\xEF\xBB\xBFname,sku,barcode,category,description\r\n"
            .'"Beans, white",SD-001,001234,"Grains, Legumes & Seeds > Bulk Bags","Label says ""premium"""'."\r\n";

        $row = CatalogueCsv::parse($csv)[0];

        $this->assertSame('Beans, white', $row['name']);
        $this->assertSame('SD-001', $row['sku']);
        $this->assertSame('001234', $row['barcode']);
        $this->assertSame('Grains, Legumes & Seeds > Bulk Bags', $row['category']);
        $this->assertSame('Label says "premium"', $row['description']);
    }

    public function test_merchant_csv_preserves_quoted_columns_and_numeric_fields(): void
    {
        $csv = "\xEF\xBB\xBFname,sku,category,price,stock\n"
            .'"Beans, white",SD-001,"Grains, Legumes & Seeds > Bulk Bags",1500,12';

        $row = ProductCsv::parse($csv)[0];

        $this->assertSame('Beans, white', $row['name']);
        $this->assertSame('SD-001', $row['sku']);
        $this->assertSame('Grains, Legumes & Seeds > Bulk Bags', $row['category']);
        $this->assertSame('1500', $row['price']);
        $this->assertSame('12', $row['currentStock']);
    }

    public function test_old_template_and_multiline_descriptions_remain_supported(): void
    {
        $rows = CatalogueCsv::parse("name,category,description\nMilk,Beverages,\"Line one\nLine two\"\n\n");

        $this->assertCount(1, $rows);
        $this->assertNull($rows[0]['sku']);
        $this->assertSame("Line one\nLine two", $rows[0]['description']);
    }
}
