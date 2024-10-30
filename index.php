<?php

require 'vendor/autoload.php';

use PhpParser\Error;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use PhpParser\NodeTraverser;

class CamelCaseVisitor extends NodeVisitorAbstract
{
  public function enterNode(Node $node)
  {
    // Only process variable names (Node\Expr\Variable)
    if ($node instanceof Node\Expr\Variable && is_string($node->name)) {
      $camelCaseName = $this->toCamelCase($node->name);
      if ($camelCaseName !== $node->name) {
        $node->name = $camelCaseName; // Update the variable name
      }
    }
  }

  // Convert snake_case or other non-camel to camelCase
  private function toCamelCase($name)
  {
    return lcfirst(str_replace('_', '', ucwords($name, '_')));
  }
}

// Sample PHP code to parse
$code = <<<'CODE'
<?php
$some_var = 1;
$another_example_var = 2;
echo $some_var + $another_example_var;
CODE;

$code = file_get_contents('../m/__get.php');
print($code);
print(PHP_EOL);
// Parse the code
$parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
try {
  $ast = $parser->parse($code);
} catch (Error $e) {
  echo "Parse error: {$e->getMessage()}\n";
  exit(1);
}

// Traverse and modify the AST
$traverser = new NodeTraverser();
$traverser->addVisitor(new CamelCaseVisitor());
$modifiedAst = $traverser->traverse($ast);

// Pretty-print the modified code
$prettyPrinter = new Standard();
$newCode = $prettyPrinter->prettyPrintFile($modifiedAst);
file_put_contents('../m/__get.php', $newCode);
echo "Modified Code:\n$newCode\n";
