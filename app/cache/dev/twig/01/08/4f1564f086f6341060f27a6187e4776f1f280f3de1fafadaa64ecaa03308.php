<?php

/* CurrencyConverterCurrencyConverterBundle:Home:index.html.twig */
class __TwigTemplate_01084f1564f086f6341060f27a6187e4776f1f280f3de1fafadaa64ecaa03308 extends Twig_Template
{
    public function __construct(Twig_Environment $env)
    {
        parent::__construct($env);

        $this->parent = $this->env->loadTemplate("CurrencyConverterCurrencyConverterBundle::layout.html.twig");

        $this->blocks = array(
            'body' => array($this, 'block_body'),
        );
    }

    protected function doGetParent(array $context)
    {
        return "CurrencyConverterCurrencyConverterBundle::layout.html.twig";
    }

    protected function doDisplay(array $context, array $blocks = array())
    {
        $this->parent->display($context, array_merge($this->blocks, $blocks));
    }

    // line 3
    public function block_body($context, array $blocks = array())
    {
        // line 4
        echo "    homepage content
";
    }

    public function getTemplateName()
    {
        return "CurrencyConverterCurrencyConverterBundle:Home:index.html.twig";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  31 => 4,  28 => 3,);
    }
}
