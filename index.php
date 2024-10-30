<?php

require 'vendor/autoload.php';

use PhpParser\Error;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use PhpParser\NodeTraverser;
use PhpParser\PrettyPrinter\Standard;

class CamelCaseVisitor extends NodeVisitorAbstract
{
    private $replacements = [];

    public function enterNode(Node $node)
    {
        // Only process variables that aren't superglobals (no leading underscore)
        if ($node instanceof Node\Expr\Variable && is_string($node->name)) {
            if (strpos($node->name, '_') === 0) { 
                return; // Ignore variables like $_GET, $_POST, etc. but process var like $Offer_Data
            }

            $camelCaseName = $this->toCamelCase($node->name);
            if ($camelCaseName !== $node->name) {
                // Store the original and new variable name for replacement
                $this->replacements[$node->name] = $camelCaseName;
            }
        }
    }

    private function toCamelCase($name)
    {
        return lcfirst(str_replace('_', '', ucwords($name, '_')));
    }

    // Replace variables in the original code using the stored replacements
    public function getModifiedCode($originalCode)
    {
        $modifiedCode = $originalCode;
        foreach ($this->replacements as $oldName => $newName) {
            // Use word boundaries (\b) to avoid partial replacements
            $modifiedCode = preg_replace('/\b' . preg_quote($oldName, '/') . '\b/', $newName, $modifiedCode);
        }
        return $modifiedCode;
    }
}

$code = file_get_contents('../m/coupon.php');

$parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
try {
    $ast = $parser->parse($code);
} catch (Error $e) {
    echo "Parse error: {$e->getMessage()}\n";
    exit(1);
}

// Traverse and modify the AST
$traverser = new NodeTraverser();
$visitor = new CamelCaseVisitor();
$traverser->addVisitor($visitor);
$traverser->traverse($ast);

// Print the modified code without formatting changes
$modifiedCode = $visitor->getModifiedCode($code);
echo "Modified Code:\n$modifiedCode\n";
file_put_contents('../m/coupon.php', $modifiedCode);