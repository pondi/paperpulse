<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Merchant;

class MerchantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $merchantNames = [
            'Kiwi',
            'Rema 1000',
            'Coop',
            'Meny',
            'Spar',
            'Bunnpris',
            'Joker',
            'Narvesen',
            '7-Eleven',
            'Shell',
            'Statoil',
            'Circle K',
            'Esso',
            'ICA',
            'Rimi',
            'Europris',
            'Vinmonopolet',
            'Elkjøp',
            'Power',
            'Komplett',
            'NetOnNet',
            'Clas Ohlson',
            'Jula',
            'Biltema',
            'XXL',
            'G-Sport',
            'Intersport',
            'Sport 1',
            'H&M',
            'Zara',
            'Cubus',
            'Dressmann',
            'Bik Bok',
            'Gina Tricot',
            'Lindex',
            'KappAhl',
            'Vero Moda',
            'Jack & Jones',
            'IKEA',
            'Jysk',
            'Møbelringen',
            'Skeidar',
            'Bohus',
            'Plantasjen',
            'Mester Grønn',
            'Interflora',
            'Boots Apotek',
            'Apotek 1',
            'Vitusapotek',
            'Dolly Dimple’s',
            'Peppes Pizza',
            'Burger King',
            'McDonald’s',
            'Subway',
            'Max Burgers',
            'Starbucks',
            'Wayne’s Coffee',
            'Espresso House',
            '7-Eleven',
            'YX',
            'Best',
            'Delidisk',
            'Mix',
            'Tiger',
            'Flying Tiger Copenhagen',
            'Normal',
            'Søstrene Grene',
            'Panduro',
            'Nille',
            'Rusta',
            'Øyo',
            'Helly Hansen',
            'Moods of Norway',
            'Stormberg',
            'Bergans',
            'Devold',
            'Dale of Norway',
            'Viking Footwear',
            'A-Hjort',
            'Mona Strand',
            'Mestergull',
            'Gullfunn',
            'David-Andersen',
            'Bjørklund',
            'Thune',
            'Christiania Glasmagasin',
            'Kitch’n',
            'Tilbords',
            'Glass Thomsen',
            'Illums Bolighus',
            'Home & Cottage',
            'Kid Interiør',
            'Princess',
            'Riviera Maison',
            'Søstrene Grene',
            'Kremmerhuset',
            'Bolia',
            'Slettvoll',
            'Montana',
            'BoConcept',
            'Brunstad',
            'Ekornes',
            'Hødnebø',
            'Huseby',
            'Norema',
            'Sigdal',
            'Epoq',
            'Marbodal',
            'Drømmekjøkkenet',
            'Strai',
            'Häcker',
            'Kvik',
            'Designa',
            'Nobia',
            'HTH',
            'Invita',
            'Aubo',
            'JKE Design',
            'Svane',
            'Kjøkkenforum',
            'Kjøkkenskapet',
            'Kjøkkenfornyeren',
        ];

        foreach ($merchantNames as $merchantName) {
            Merchant::create(['name' => $merchantName]);
        }
    }
}
