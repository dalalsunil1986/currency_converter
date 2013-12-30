<?php

namespace CurrencyConverter\CurrencyConverterBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="CurrencyConverter\CurrencyConverterBundle\Entity\Repository\CurrencyRepository")
 * @ORM\Table(name="currencies")
 */
class Currency
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;
    
     /**
     * @ORM\OneToOne(targetEntity="Country", mappedBy="currency")     * 
     */
    protected $country;

    /**
     * @ORM\Column(type="string", length=120)
     */
    protected $currency;

    /**
     * @ORM\Column(type="string", length=50)
     */
    protected $code;

     /**
     * @ORM\Column(type="string", length=50)
     */
    protected $symbol;


    /**
     * Get id
     *
     * @return integer 
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set currency
     *
     * @param string $currency
     * @return Currency
     */
    public function setCurrency($currency)
    {
        $this->currency = $currency;

        return $this;
    }

    /**
     * Get currency
     *
     * @return string 
     */
    public function getCurrency()
    {
        return $this->currency;
    }

    /**
     * Set code
     *
     * @param string $code
     * @return Currency
     */
    public function setCode($code)
    {
        $this->code = $code;

        return $this;
    }

    /**
     * Get code
     *
     * @return string 
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * Set symbol
     *
     * @param string $symbol
     * @return Currency
     */
    public function setSymbol($symbol)
    {
        $this->symbol = $symbol;

        return $this;
    }

    /**
     * Get symbol
     *
     * @return string 
     */
    public function getSymbol()
    {
        return $this->symbol;
    }

    /**
     * Set country
     *
     * @param \CurrencyConverter\CurrencyConverterBundle\Entity\Country $country
     * @return Currency
     */
    public function setCountry(\CurrencyConverter\CurrencyConverterBundle\Entity\Country $country = null)
    {
        $this->country = $country;

        return $this;
    }

    /**
     * Get country
     *
     * @return \CurrencyConverter\CurrencyConverterBundle\Entity\Country 
     */
    public function getCountry()
    {
        return $this->country;
    }
}
