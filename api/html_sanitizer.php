<?php
/**
 * Shared by the admin API (CMS save) and the public site (CMS render),
 * so stored page content is cleaned on the way in and on the way out.
 */

if (!function_exists('sanitize_html')) {

    /**
     * Allowlist HTML sanitizer for rich-text (CMS) content: keeps basic
     * formatting tags, safe links/images and Quill alignment classes; drops
     * scripts, event handlers, styles and javascript:/data: URLs.
     */
    function sanitize_html(string $html): string
    {
        $allowed = [
            'h2' => [], 'h3' => [], 'h4' => [], 'p' => ['class'], 'br' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [], 's' => [],
            'a' => ['href', 'target', 'rel'], 'ul' => [], 'ol' => [], 'li' => ['class'], 'blockquote' => [], 'img' => ['src', 'alt'], 'span' => ['class'],
        ];
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="zc-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        $root = $doc->getElementById('zc-root');
        if (!$root) {
            return '';
        }

        $clean = function (DOMNode $node) use (&$clean, $allowed, $doc) {
            for ($i = $node->childNodes->length - 1; $i >= 0; $i--) {
                $child = $node->childNodes->item($i);
                if ($child instanceof DOMElement) {
                    $tag = strtolower($child->tagName);
                    if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'textarea', 'select', 'link', 'meta'], true)) {
                        $node->removeChild($child);
                        continue;
                    }
                    $clean($child);
                    if (!isset($allowed[$tag])) {
                        while ($child->firstChild) {
                            $node->insertBefore($child->firstChild, $child);
                        }
                        $node->removeChild($child);
                        continue;
                    }
                    for ($a = $child->attributes->length - 1; $a >= 0; $a--) {
                        $attr = $child->attributes->item($a);
                        $name = strtolower($attr->name);
                        $value = trim($attr->value);
                        $keep = in_array($name, $allowed[$tag], true);
                        if ($keep && in_array($name, ['href', 'src'], true)) {
                            $keep = (bool) preg_match('#^(https?://|mailto:|tel:|/|\./|\.\./|[a-z0-9_-]+\.php|[a-z0-9_./-]+\.(png|jpe?g|webp|gif))#i', $value)
                                && !preg_match('#^\s*(javascript|data|vbscript):#i', $value);
                        }
                        if ($keep && $name === 'class') {
                            $classes = array_filter(preg_split('/\s+/', $value), fn($c) => preg_match('/^ql-[a-z0-9-]+$/', $c));
                            $keep = (bool) $classes;
                            $value = implode(' ', $classes);
                        }
                        if ($keep && $name === 'target') {
                            $value = '_blank';
                        }
                        if ($keep) {
                            $child->setAttribute($attr->name, $value);
                        } else {
                            $child->removeAttribute($attr->name);
                        }
                    }
                    if ($tag === 'a' && $child->getAttribute('target') === '_blank') {
                        $child->setAttribute('rel', 'noopener noreferrer');
                    }
                } elseif ($child instanceof DOMComment) {
                    $node->removeChild($child);
                }
            }
        };
        $clean($root);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }
        return trim($out);
    }

}
