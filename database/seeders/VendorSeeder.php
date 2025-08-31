<?php

namespace Database\Seeders;

use App\Models\Vendor;
use Illuminate\Database\Seeder;

class VendorSeeder extends Seeder
{
    public function run(): void
    {
        $vendors = [
            // Elektronikk
            ['name' => 'Philips', 'website' => 'https://www.philips.no'],
            ['name' => 'LG Electronics', 'website' => 'https://www.lg.com/no'],
            ['name' => 'Samsung', 'website' => 'https://www.samsung.com/no'],
            ['name' => 'Sony', 'website' => 'https://www.sony.no'],
            ['name' => 'Apple', 'website' => 'https://www.apple.com/no'],
            ['name' => 'Bosch', 'website' => 'https://www.bosch-home.no'],
            ['name' => 'Siemens', 'website' => 'https://www.siemens-home.bsh-group.com/no'],
            ['name' => 'Miele', 'website' => 'https://www.miele.no'],
            ['name' => 'Electrolux', 'website' => 'https://www.electrolux.no'],
            ['name' => 'Whirlpool', 'website' => 'https://www.whirlpool.no'],

            // Kjøkkenutstyr
            ['name' => 'OXO', 'website' => 'https://www.oxo.com'],
            ['name' => 'KitchenAid', 'website' => 'https://www.kitchenaid.no'],
            ['name' => 'Kenwood', 'website' => 'https://www.kenwoodworld.com/no-no'],
            ['name' => 'Wilfa', 'website' => 'https://wilfa.no'],
            ['name' => 'Braun', 'website' => 'https://www.braunhousehold.com/no-no'],
            ['name' => 'Tefal', 'website' => 'https://www.tefal.no'],
            ['name' => 'Weber', 'website' => 'https://www.weber.com/NO/no/home/'],
            ['name' => 'Fiskars', 'website' => 'https://www.fiskars.com/no-no'],

            // Mat og drikke
            ['name' => 'Nestlé', 'website' => 'https://www.nestle.no'],
            ['name' => 'Orkla', 'website' => 'https://www.orkla.no'],
            ['name' => 'Tine', 'website' => 'https://www.tine.no'],
            ['name' => 'Mills', 'website' => 'https://www.mills.no'],
            ['name' => 'Coca-Cola', 'website' => 'https://www.coca-cola.no'],
            ['name' => 'Pepsi', 'website' => 'https://www.pepsico.com'],
            ['name' => 'Freia', 'website' => 'https://www.freia.no'],
            ['name' => 'Nidar', 'website' => 'https://www.nidar.no'],

            // Personlig pleie
            ['name' => 'Medela', 'website' => 'https://www.medela.no'],
            ['name' => 'Gillette', 'website' => 'https://gillette.com/nb-no'],
            ['name' => 'L\'Oréal', 'website' => 'https://www.loreal.com/no-no'],
            ['name' => 'Nivea', 'website' => 'https://www.nivea.no'],
            ['name' => 'Colgate', 'website' => 'https://www.colgate.com/nb-no'],
            ['name' => 'Jordan', 'website' => 'https://www.jordan.no'],
            ['name' => 'Veet', 'website' => 'https://www.veet.no'],
            ['name' => 'Dove', 'website' => 'https://www.dove.com/no'],

            // Rengjøring
            ['name' => 'Jif', 'website' => 'https://www.jif.no'],
            ['name' => 'Omo', 'website' => 'https://www.omo.com/no'],
            ['name' => 'Comfort', 'website' => 'https://www.comfort.no'],
            ['name' => 'Blenda', 'website' => 'https://www.blenda.no'],
            ['name' => 'Ajax', 'website' => 'https://www.ajax.com'],
            ['name' => 'Kiwi', 'website' => 'https://www.kiwigrip.no'],
            ['name' => 'Zalo', 'website' => 'https://www.lilleborg.no/vare-merkevarer/zalo/'],

            // Sport og fritid
            ['name' => 'Nike', 'website' => 'https://www.nike.com/no'],
            ['name' => 'Adidas', 'website' => 'https://www.adidas.no'],
            ['name' => 'Puma', 'website' => 'https://no.puma.com'],
            ['name' => 'Under Armour', 'website' => 'https://www.underarmour.no'],
            ['name' => 'The North Face', 'website' => 'https://www.thenorthface.no'],
            ['name' => 'Bergans', 'website' => 'https://www.bergans.com'],
            ['name' => 'Norrøna', 'website' => 'https://www.norrona.com'],
            ['name' => 'Helly Hansen', 'website' => 'https://www.hellyhansen.com/no_no'],

            // Leker og spill
            ['name' => 'LEGO', 'website' => 'https://www.lego.com/nb-no'],
            ['name' => 'Hasbro', 'website' => 'https://hasbro.com'],
            ['name' => 'Mattel', 'website' => 'https://www.mattel.com'],
            ['name' => 'Nintendo', 'website' => 'https://www.nintendo.no'],
            ['name' => 'PlayStation', 'website' => 'https://www.playstation.com/no-no'],
            ['name' => 'Xbox', 'website' => 'https://www.xbox.com/nb-NO'],

            // Møbler og interiør
            ['name' => 'IKEA', 'website' => 'https://www.ikea.com/no'],
            ['name' => 'Bolia', 'website' => 'https://www.bolia.com/nb-no'],
            ['name' => 'HAY', 'website' => 'https://hay.no'],
            ['name' => 'Muuto', 'website' => 'https://muuto.com'],
            ['name' => 'Menu', 'website' => 'https://menu.as'],
            ['name' => 'Vitra', 'website' => 'https://www.vitra.com'],
        ];

        foreach ($vendors as $vendor) {
            Vendor::create($vendor);
        }
    }
}
