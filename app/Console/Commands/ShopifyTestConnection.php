<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:shopify-test-connection')]
#[Description('Command description')]
class ShopifyTestConnection extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        //
    }
}
