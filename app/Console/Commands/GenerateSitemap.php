<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

class GenerateSitemap extends Command
{
    protected $signature = 'sitemap:generate';

    protected $description = 'Génère le fichier sitemap.xml';

    public function handle()
    {
        Sitemap::create()
            ->add(Url::create('/'))
            ->add(Url::create('/recherche-vehicule'))
            ->add(Url::create('/contact'))
            ->add(Url::create('/condition-de-vente'))
            ->add(Url::create('/suivre-commande'))
            ->add(Url::create('/bon'))
            ->add(Url::create('/bon-commande'))
            ->writeToFile(public_path('sitemap.xml'));

        $this->info('Sitemap généré avec succès.');
        
        
    }
    
}
