<?php

namespace Victoire\Bundle\I18nBundle\Translation;

use Symfony\Bundle\FrameworkBundle\Translation\Translator as BaseTranslator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Translation\MessageSelector;

class Translator extends BaseTranslator
{
    protected $container;
    protected $options = [
        'cache_dir'      => 'test',
        'debug'          => true,
        'resource_files' => [],
    ];
    protected $loaderIds;

    /**
     * @var MessageSelector
     */
    private $selector;

    /**
     * {@inheritdoc}
     *
     * @api
     */
    public function __construct(ContainerInterface $container, MessageSelector $selector, $loaderIds = [], array $options = [])
    {
        parent::__construct($container, $selector, $loaderIds, $options);
        $this->selector = $selector;
        $this->container = $container;
    }

    /**
     * {@inheritdoc}
     *
     * @api
     */
    public function trans($id, array $parameters = [], $domain = null, $locale = null)
    {
        if (null === $locale) {
            $locale = $this->getLocale();
        }
        if (null === $domain) {
            $domain = 'messages';
        } elseif (in_array($domain, $this->container->getParameter('victoire_i18n.users_locale.domains'))) {
            $locale = $this->getVictoireLocale();
        }
        if (!isset($this->catalogues[$locale])) {
            $this->loadCatalogue($locale);
        }

        return strtr($this->catalogues[$locale]->get((string) $id, $domain), $parameters);
    }

    /**
     * {@inheritdoc}
     *
     * @api
     */
    public function transChoice($id, $number, array $parameters = [], $domain = null, $locale = null)
    {
        if (null === $locale) {
            $locale = $this->getLocale();
        }
        if (null === $domain) {
            $domain = 'messages';
        } elseif (in_array($domain, $this->container->getParameter('victoire_i18n.users_locale.domains'))) {
            $locale = $this->getVictoireLocale();
        }
        if (!isset($this->catalogues[$locale])) {
            $this->loadCatalogue($locale);
        }
        $id = (string) $id;
        $catalogue = $this->catalogues[$locale];
        while (!$catalogue->defines($id, $domain)) {
            if ($cat = $catalogue->getFallbackCatalogue()) {
                $catalogue = $cat;
                $locale = $catalogue->getLocale();
            } else {
                break;
            }
        }

        return strtr($this->selector->choose($catalogue->get($id, $domain), (int) $number, $locale), $parameters);
    }

    /**
     * get the local in the session.
     */
    public function getLocale()
    {
        $this->locale = $this->getCurrentLocale();

        return $this->locale;
    }

    /**
     * get the locale of the administration template.
     *
     * @return string
     */
    public function getVictoireLocale()
    {
        $this->locale = $this->container->get('session')->get('victoire_locale');

        return $this->locale;
    }

    /**
     * @return mixed|string
     */
    public function getCurrentLocale()
    {
        if ($this->container->get('request_stack')->getCurrentRequest()) {
            return $this->container->get('request')->getLocale();
        }

        return $this->container->getParameter('kernel.default_locale');
    }
}
