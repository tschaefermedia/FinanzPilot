<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        if (Category::count() > 0) {
            return;
        }

        $categories = [
            ['name' => 'Einnahmen', 'type' => 'income', 'icon' => 'pi-arrow-down-left', 'children' => [
                ['name' => 'Gehalt'], ['name' => 'Nebeneinkommen'], ['name' => 'Sonstiges'],
            ]],
            ['name' => 'Wohnen', 'type' => 'expense', 'icon' => 'pi-home', 'children' => [
                ['name' => 'Miete'], ['name' => 'Nebenkosten'], ['name' => 'Strom'], ['name' => 'Internet'],
            ]],
            ['name' => 'Lebensmittel', 'type' => 'expense', 'icon' => 'pi-shopping-cart', 'children' => [
                ['name' => 'Supermarkt'], ['name' => 'Restaurant'], ['name' => 'Lieferdienst'],
            ]],
            ['name' => 'Mobilität', 'type' => 'expense', 'icon' => 'pi-car', 'children' => [
                ['name' => 'ÖPNV'], ['name' => 'Tanken'], ['name' => 'KFZ-Versicherung'], ['name' => 'Werkstatt'],
            ]],
            ['name' => 'Gesundheit', 'type' => 'expense', 'icon' => 'pi-heart', 'children' => [
                ['name' => 'Arzt'], ['name' => 'Apotheke'], ['name' => 'Krankenversicherung'],
            ]],
            ['name' => 'Freizeit', 'type' => 'expense', 'icon' => 'pi-sun', 'children' => [
                ['name' => 'Sport'], ['name' => 'Kultur'], ['name' => 'Hobbys'], ['name' => 'Urlaub'],
            ]],
            ['name' => 'Kleidung', 'type' => 'expense', 'icon' => 'pi-tag', 'children' => []],
            ['name' => 'Versicherungen', 'type' => 'expense', 'icon' => 'pi-shield', 'children' => [
                ['name' => 'Haftpflicht'], ['name' => 'Hausrat'], ['name' => 'Berufsunfähigkeit'],
            ]],
            ['name' => 'Sparen & Investieren', 'type' => 'expense', 'icon' => 'pi-chart-line', 'children' => [
                ['name' => 'Tagesgeld'], ['name' => 'ETF'], ['name' => 'Krypto'],
            ]],
            ['name' => 'Übertragungen', 'type' => 'transfer', 'icon' => 'pi-arrows-h', 'children' => []],
        ];

        foreach ($categories as $order => $cat) {
            $parent = Category::create([
                'name' => $cat['name'],
                'type' => $cat['type'],
                'icon' => $cat['icon'],
                'sort_order' => $order,
            ]);

            foreach ($cat['children'] as $childOrder => $child) {
                Category::create([
                    'name' => $child['name'],
                    'type' => $cat['type'],
                    'icon' => $cat['icon'],
                    'parent_id' => $parent->id,
                    'sort_order' => $childOrder,
                ]);
            }
        }
    }
}
