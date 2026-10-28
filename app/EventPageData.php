<?php

namespace App;

use App\Models\Donation;
use App\Models\Sponsor;
use Illuminate\Http\Request;

class EventPageData
{
    public function __construct(private Request $request) {}

    /**
     * @return array{page: array<string, string>, event: array<string, mixed>, navigation: list<array{label: string, href: string, route: string}>, accountNavigation: list<array{label: string, href: string, route: string}>, socials: list<array{label: string, icon: string, href: string}>, links: array<string, string>, currentRoute: string}
     */
    public function forPage(string $pageName): array
    {
        $pages = config('event.pages');
        $page = $pages[$pageName];
        $event = config('event.event');

        $event['images'] = array_map(fn (string $path): string => asset($path), $event['images']);

        if (! $event['fundraising']['isPreview']) {
            $event['fundraising']['raisedCents'] = Donation::completedAmountCents();
            $sponsors = Sponsor::query()
                ->orderBy('company_name')
                ->orderBy('id')
                ->get(['company_name', 'sponsorship_type'])
                ->groupBy('sponsorship_type');

            foreach ($event['sponsorTiers'] as $index => $tier) {
                $event['sponsorTiers'][$index]['sponsors'] = $sponsors->get($tier['id'], collect())
                    ->map(fn (Sponsor $sponsor): array => ['name' => $sponsor->company_name, 'url' => null, 'logo' => null])
                    ->values()
                    ->all();
            }
        }

        $navigation = [];
        $links = [];

        foreach ($pages as $name => $definition) {
            $links[$name] = route($definition['route']);
            $navigation[] = ['label' => $definition['label'], 'href' => $links[$name], 'route' => $definition['route']];
        }

        $links['account'] = route('account.index');
        $links['login'] = route('login');
        $links['passwordLogin'] = route('password.login');
        $links['registration'] = route('registration.create');

        $accountRoutes = $this->request->user()
            ? ['account.index' => 'Mijn account']
            : ['login' => 'Inloggen', 'registration.create' => 'Registreren'];
        $accountNavigation = [];

        foreach ($accountRoutes as $route => $label) {
            $accountNavigation[] = ['label' => $label, 'href' => route($route), 'route' => $route];
        }

        return [
            'page' => [
                'name' => $pageName,
                'heading' => $page['heading'],
                'description' => $page['description'],
                'documentTitle' => $page['label'].' — '.$event['name'],
                'canonical' => $links[$pageName],
            ],
            'event' => $event,
            'navigation' => $navigation,
            'accountNavigation' => $accountNavigation,
            'socials' => config('event.socials'),
            'links' => $links,
            'currentRoute' => $this->request->route()->getName(),
        ];
    }
}
