<?php

require 'vendor/autoload.php';

use PhpParser\Error;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use PhpParser\NodeTraverser;

class CamelCaseVisitor extends NodeVisitorAbstract
{
    public $replacements = [];
    private $globalVariables = [];

    public function enterNode(Node $node)
    {
        // Track variables defined as global
        if ($node instanceof Node\Stmt\Global_) {
            foreach ($node->vars as $var) {
                if ($var instanceof Node\Expr\Variable && is_string($var->name)) {
                    $this->globalVariables[] = $var->name;
                }
            }
        }

        // Only process variables that aren't superglobals or globals
        if ($node instanceof Node\Expr\Variable && is_string($node->name)) {
            if (strpos($node->name, '_') === 0 || in_array($node->name, $this->globalVariables, true)) {
                return; // Ignore superglobals and global variables
            }

            $camelCaseName = $this->toCamelCase($node->name);
            if ($camelCaseName !== $node->name) {
                // Store the original and new variable name for replacement
                $this->replacements['$' . $node->name] = '$' . $camelCaseName;
            }
        }
    }

    private function toCamelCase($name)
    {
        if (strtoupper($name) === $name) {
            return $name;
        }
        if ($name == 'Root_Account_Data') {
            return $name;
        }
        return lcfirst(str_replace('_', '', ucwords($name, '_')));
    }
}

// Load and parse the code
$FILE = 'settings.php';
$code = file_get_contents('../m/' . $FILE);

$parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
try {
    $ast = $parser->parse($code);
} catch (Error $e) {
    echo "Parse error: {$e->getMessage()}\n";
    exit(1);
}

// Traverse and collect variable replacements
$traverser = new NodeTraverser();
$visitor = new CamelCaseVisitor();
$traverser->addVisitor($visitor);
$traverser->traverse($ast);

// Tokenize code and replace variable names based on collected replacements
$tokens = token_get_all($code);
$modifiedCode = '';

foreach ($tokens as $token) {
    if (is_array($token)) {
        list($id, $text) = $token;

        // Replace variable names only if they match
        if ($id === T_VARIABLE && isset($visitor->replacements[$text])) {
            $text = $visitor->replacements[$text];
        }
        $modifiedCode .= $text;
    } else {
        // Append non-array tokens directly
        $modifiedCode .= $token;
    }
}

echo "Modified Code:\n$modifiedCode\n";
file_put_contents('../m/' . $FILE, $modifiedCode);
