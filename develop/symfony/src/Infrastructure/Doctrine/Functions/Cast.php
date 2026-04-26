<?php
declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Functions;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType;

/**
 * Custom DQL CAST function for PostgreSQL.
 *
 * Usage in DQL: CAST(e.property AS text)
 */
final class Cast extends FunctionNode
{
    private Node $expression;
    private string $castType;

    public function parse(Parser $parser): void
    {
        $parser->match(TokenType::T_IDENTIFIER); // CAST
        $parser->match(TokenType::T_OPEN_PARENTHESIS);
        $this->expression = $parser->ArithmeticPrimary();
        $parser->match(TokenType::T_AS);

        $lexer = $parser->getLexer();
        $lexer->moveNext();
        $this->castType = $lexer->token->value;

        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }

    public function getSql(SqlWalker $sqlWalker): string
    {
        return sprintf('CAST(%s AS %s)', $this->expression->dispatch($sqlWalker), $this->castType);
    }
}

