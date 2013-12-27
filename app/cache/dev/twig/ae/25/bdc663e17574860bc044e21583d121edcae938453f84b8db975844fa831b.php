<?php

/* BloggerBlogBundle:Page:contact.html.twig */
class __TwigTemplate_ae25bdc663e17574860bc044e21583d121edcae938453f84b8db975844fa831b extends Twig_Template
{
    public function __construct(Twig_Environment $env)
    {
        parent::__construct($env);

        $this->parent = $this->env->loadTemplate("BloggerBlogBundle::layout.html.twig");

        $this->blocks = array(
            'title' => array($this, 'block_title'),
            'body' => array($this, 'block_body'),
        );
    }

    protected function doGetParent(array $context)
    {
        return "BloggerBlogBundle::layout.html.twig";
    }

    protected function doDisplay(array $context, array $blocks = array())
    {
        $this->parent->display($context, array_merge($this->blocks, $blocks));
    }

    // line 3
    public function block_title($context, array $blocks = array())
    {
        echo "Contact";
    }

    // line 5
    public function block_body($context, array $blocks = array())
    {
        // line 6
        echo "   <header>
        <h1>Contact symblog</h1>
    </header>
   
    ";
        // line 10
        $context['_parent'] = (array) $context;
        $context['_seq'] = twig_ensure_traversable($this->getAttribute($this->getAttribute($this->getAttribute((isset($context["app"]) ? $context["app"] : $this->getContext($context, "app")), "session"), "flashbag"), "get", array(0 => "blogger-notice"), "method"));
        foreach ($context['_seq'] as $context["_key"] => $context["flashMessage"]) {
            // line 11
            echo "        <div class=\"blogger-notice\">
             ";
            // line 12
            echo twig_escape_filter($this->env, (isset($context["flashMessage"]) ? $context["flashMessage"] : $this->getContext($context, "flashMessage")), "html", null, true);
            echo "
        </div>      
    ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['_key'], $context['flashMessage'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 15
        echo "
    <p>Want to contact symblog?</p>

    <form action=\"";
        // line 18
        echo $this->env->getExtension('routing')->getPath("BloggerBlogBundle_contact");
        echo "\" method=\"post\" ";
        echo $this->env->getExtension('form')->renderer->searchAndRenderBlock((isset($context["form"]) ? $context["form"] : $this->getContext($context, "form")), 'enctype');
        echo " class=\"blogger\">
        ";
        // line 19
        echo $this->env->getExtension('form')->renderer->searchAndRenderBlock((isset($context["form"]) ? $context["form"] : $this->getContext($context, "form")), 'errors');
        echo "

        ";
        // line 21
        echo $this->env->getExtension('form')->renderer->searchAndRenderBlock($this->getAttribute((isset($context["form"]) ? $context["form"] : $this->getContext($context, "form")), "name"), 'row');
        echo "
        ";
        // line 22
        echo $this->env->getExtension('form')->renderer->searchAndRenderBlock($this->getAttribute((isset($context["form"]) ? $context["form"] : $this->getContext($context, "form")), "email"), 'row');
        echo "
        ";
        // line 23
        echo $this->env->getExtension('form')->renderer->searchAndRenderBlock($this->getAttribute((isset($context["form"]) ? $context["form"] : $this->getContext($context, "form")), "subject"), 'row');
        echo "
        ";
        // line 24
        echo $this->env->getExtension('form')->renderer->searchAndRenderBlock($this->getAttribute((isset($context["form"]) ? $context["form"] : $this->getContext($context, "form")), "body"), 'row');
        echo "

        ";
        // line 26
        echo $this->env->getExtension('form')->renderer->searchAndRenderBlock((isset($context["form"]) ? $context["form"] : $this->getContext($context, "form")), 'rest');
        echo "

        <input type=\"submit\" value=\"Submit\" />
    </form>
";
    }

    public function getTemplateName()
    {
        return "BloggerBlogBundle:Page:contact.html.twig";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  93 => 26,  88 => 24,  84 => 23,  80 => 22,  76 => 21,  71 => 19,  65 => 18,  60 => 15,  51 => 12,  48 => 11,  44 => 10,  38 => 6,  35 => 5,  29 => 3,);
    }
}
