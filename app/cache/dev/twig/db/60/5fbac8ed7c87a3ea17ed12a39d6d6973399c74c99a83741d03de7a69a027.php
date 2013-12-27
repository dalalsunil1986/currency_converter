<?php

/* ::currency_coverter_base.html.twig */
class __TwigTemplate_db605fbac8ed7c87a3ea17ed12a39d6d6973399c74c99a83741d03de7a69a027 extends Twig_Template
{
    public function __construct(Twig_Environment $env)
    {
        parent::__construct($env);

        $this->parent = false;

        $this->blocks = array(
            'title' => array($this, 'block_title'),
            'stylesheets' => array($this, 'block_stylesheets'),
            'navigation' => array($this, 'block_navigation'),
            'site_title' => array($this, 'block_site_title'),
            'site_tagline' => array($this, 'block_site_tagline'),
            'body' => array($this, 'block_body'),
            'footer' => array($this, 'block_footer'),
            'javascripts' => array($this, 'block_javascripts'),
        );
    }

    protected function doDisplay(array $context, array $blocks = array())
    {
        // line 1
        echo "<!DOCTYPE html>
<html>
    <head>
        <meta http-equiv=\"Content-Type\" content=\"text/html\"; charset=utf-8\" />
        <title>";
        // line 5
        $this->displayBlock('title', $context, $blocks);
        echo " - Currency Converter</title>
        <!--[if lt IE 9]>
            <script src=\"http://html5shim.googlecode.com/svn/trunk/html5.js\"></script>
        <![endif]-->
        ";
        // line 9
        $this->displayBlock('stylesheets', $context, $blocks);
        // line 14
        echo "        <link rel=\"shortcut icon\" href=\"";
        echo twig_escape_filter($this->env, $this->env->getExtension('assets')->getAssetUrl("favicon.ico"), "html", null, true);
        echo "\" />
    </head>
    <body>

        <section id=\"wrapper\">
            
            <header id=\"header\">
                <div class=\"top\">
                    ";
        // line 22
        $this->displayBlock('navigation', $context, $blocks);
        // line 31
        echo "                </div>

                <hgroup>
                    <h2>";
        // line 34
        $this->displayBlock('site_title', $context, $blocks);
        echo "</h2>
                    <h3>";
        // line 35
        $this->displayBlock('site_tagline', $context, $blocks);
        echo "</h3>
                </hgroup>
            </header>

            <section class=\"main-col\">
                ";
        // line 40
        $this->displayBlock('body', $context, $blocks);
        // line 41
        echo "            </section>
           
            <div id=\"footer\">
                ";
        // line 44
        $this->displayBlock('footer', $context, $blocks);
        // line 47
        echo "            </div>
            
        </section>

        ";
        // line 51
        $this->displayBlock('javascripts', $context, $blocks);
        // line 52
        echo "    </body>
</html>";
    }

    // line 5
    public function block_title($context, array $blocks = array())
    {
        echo "Currency Converter";
    }

    // line 9
    public function block_stylesheets($context, array $blocks = array())
    {
        // line 10
        echo "            <link href='http://fonts.googleapis.com/css?family=Irish+Grover' rel='stylesheet' type='text/css'>
            <link href='http://fonts.googleapis.com/css?family=La+Belle+Aurore' rel='stylesheet' type='text/css'>
            <link href=\"";
        // line 12
        echo twig_escape_filter($this->env, $this->env->getExtension('assets')->getAssetUrl("css/currency_converter_screen.css"), "html", null, true);
        echo "\" type=\"text/css\" rel=\"stylesheet\" />
        ";
    }

    // line 22
    public function block_navigation($context, array $blocks = array())
    {
        // line 23
        echo "                        <nav>
                            <ul class=\"navigation\">
                                <li><a href=\"";
        // line 25
        echo $this->env->getExtension('routing')->getPath("currency_converter_currency_converter_homepage");
        echo "\">Home</a></li>
                                <li><a href=\"";
        // line 26
        echo $this->env->getExtension('routing')->getPath("currency_converter_currency_converter_about");
        echo "\">About</a></li>
                                <li><a href=\"";
        // line 27
        echo $this->env->getExtension('routing')->getPath("currency_converter_currency_converter_contact");
        echo "\">Contact</a></li>
                            </ul>
                        </nav>
                    ";
    }

    // line 34
    public function block_site_title($context, array $blocks = array())
    {
        echo "<a href=\"";
        echo $this->env->getExtension('routing')->getPath("currency_converter_currency_converter_homepage");
        echo "\">Currency Converter</a>";
    }

    // line 35
    public function block_site_tagline($context, array $blocks = array())
    {
        echo "<a href=\"";
        echo $this->env->getExtension('routing')->getPath("currency_converter_currency_converter_about");
        echo "\">My simple project in Symfony......</a>";
    }

    // line 40
    public function block_body($context, array $blocks = array())
    {
    }

    // line 44
    public function block_footer($context, array $blocks = array())
    {
        // line 45
        echo "                    created by <a href=\"http://www.linkedin.com/pub/joel-capillo/26/8b4/ba3\">jcapillo</a>
                ";
    }

    // line 51
    public function block_javascripts($context, array $blocks = array())
    {
    }

    public function getTemplateName()
    {
        return "::currency_coverter_base.html.twig";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  166 => 51,  161 => 45,  158 => 44,  153 => 40,  145 => 35,  137 => 34,  129 => 27,  125 => 26,  121 => 25,  117 => 23,  114 => 22,  108 => 12,  104 => 10,  101 => 9,  95 => 5,  90 => 52,  88 => 51,  82 => 47,  80 => 44,  75 => 41,  73 => 40,  65 => 35,  61 => 34,  56 => 31,  54 => 22,  42 => 14,  40 => 9,  33 => 5,  27 => 1,  36 => 5,  31 => 4,  28 => 3,);
    }
}
