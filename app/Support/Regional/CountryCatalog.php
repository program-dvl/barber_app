<?php

namespace App\Support\Regional;

use DateTimeZone;
use NumberFormatter;
use ResourceBundle;

class CountryCatalog
{
    private const NON_ISO_REGIONS = ['AC', 'CP', 'CQ', 'DG', 'EA', 'EU', 'EZ', 'IC', 'QO', 'TA', 'UN', 'XA', 'XB', 'XK', 'ZZ'];

    /** @return array<string, string> */
    public function countries(string $locale = 'en'): array
    {
        $bundle = ResourceBundle::create($locale, 'ICUDATA-region')?->get('Countries');
        $countries = [];

        if ($bundle instanceof ResourceBundle) {
            foreach ($bundle as $code => $name) {
                if (is_string($code)
                    && preg_match('/^[A-Z]{2}$/', $code)
                    && ! in_array($code, self::NON_ISO_REGIONS, true)
                    && is_string($name)) {
                    $countries[$code] = $name;
                }
            }
        }

        asort($countries, SORT_NATURAL | SORT_FLAG_CASE);

        return $countries;
    }

    /**
     * Regional suggestions are conveniences, not locked policy. The owner may
     * choose a different currency, locale, or IANA zone before saving.
     *
     * @return array<string, array{currency:?string,time_zones:list<string>}>
     */
    public function defaults(): array
    {
        return collect(array_keys($this->countries()))
            ->mapWithKeys(fn (string $country) => [$country => [
                'currency' => $this->currency($country),
                'time_zones' => array_values(DateTimeZone::listIdentifiers(DateTimeZone::PER_COUNTRY, $country)),
            ]])
            ->all();
    }

    /** @return array<string, string> */
    public function currencies(string $locale = 'en'): array
    {
        $currencies = [];

        foreach ($this->defaults() as $defaults) {
            $code = $defaults['currency'];
            if (! $code || $code === 'XXX') {
                continue;
            }

            $currencies[$code] = $code;
        }

        ksort($currencies);

        return $currencies;
    }

    private function currency(string $country): ?string
    {
        $formatter = new NumberFormatter('en_'.$country, NumberFormatter::CURRENCY);
        $currency = strtoupper((string) $formatter->getTextAttribute(NumberFormatter::CURRENCY_CODE));

        return preg_match('/^[A-Z]{3}$/', $currency) && $currency !== 'XXX' ? $currency : null;
    }
}
