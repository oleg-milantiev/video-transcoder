<?php

declare(strict_types=1);

namespace App\Tests\Application\Command\Message;

use App\Application\Command\Message\TelegramMessage;
use PHPUnit\Framework\TestCase;

final class TelegramMessageTest extends TestCase
{
    public function testConstructorSetsProperties(): void
    {
        $chatId = 123456789;
        $text = 'Test message';
        $silent = false;

        $message = new TelegramMessage($chatId, $text, $silent);

        $this->assertSame($chatId, $message->chatId);
        $this->assertSame($text, $message->text);
        $this->assertFalse($message->silent);
    }

    public function testConstructorWithSilentTrue(): void
    {
        $chatId = 987654321;
        $text = 'Silent notification';
        $silent = true;

        $message = new TelegramMessage($chatId, $text, $silent);

        $this->assertSame($chatId, $message->chatId);
        $this->assertSame($text, $message->text);
        $this->assertTrue($message->silent);
    }

    public function testMessageIsReadonly(): void
    {
        $message = new TelegramMessage(123, 'Original message', false);

        // Attempting to modify readonly properties should result in error
        try {
            $message->chatId = 456;
            $this->fail('Should not allow property modification');
        } catch (\Error $e) {
            $this->assertStringContainsString('Cannot modify readonly', $e->getMessage());
        }
    }

    public function testMessageWithHtmlFormattedText(): void
    {
        $chatId = 111111111;
        $text = '<b>Bold text</b>\n<i>Italic text</i>\n<code>code</code>';
        $silent = false;

        $message = new TelegramMessage($chatId, $text, $silent);

        $this->assertSame($text, $message->text);
    }

    public function testMessageWithEmoji(): void
    {
        $chatId = 222222222;
        $text = '✅ Task completed\n⚠️ Warning message\n❌ Error occurred';
        $silent = false;

        $message = new TelegramMessage($chatId, $text, $silent);

        $this->assertSame($text, $message->text);
    }

    public function testMessageWithLongText(): void
    {
        $chatId = 333333333;
        $text = str_repeat('A', 1000); // Long message
        $silent = false;

        $message = new TelegramMessage($chatId, $text, $silent);

        $this->assertSame($text, $message->text);
        $this->assertStringStartsWith('A', $message->text);
    }

    public function testMessageWithNegativeChatId(): void
    {
        $chatId = -123456789; // Negative chat ID (private chat)
        $text = 'Test message';
        $silent = false;

        $message = new TelegramMessage($chatId, $text, $silent);

        $this->assertSame($chatId, $message->chatId);
    }

    public function testMessageWithEmptyText(): void
    {
        $chatId = 444444444;
        $text = '';
        $silent = false;

        $message = new TelegramMessage($chatId, $text, $silent);

        $this->assertSame('', $message->text);
    }

    public function testMultipleMessagesIndependent(): void
    {
        $message1 = new TelegramMessage(111, 'Message 1', false);
        $message2 = new TelegramMessage(222, 'Message 2', true);

        $this->assertSame(111, $message1->chatId);
        $this->assertSame(222, $message2->chatId);
        $this->assertSame('Message 1', $message1->text);
        $this->assertSame('Message 2', $message2->text);
        $this->assertFalse($message1->silent);
        $this->assertTrue($message2->silent);
    }

    public function testMessageWithUnicodeCharacters(): void
    {
        $chatId = 555555555;
        $text = 'Привет мир 🌍 こんにちは 👋 مرحبا العالم';
        $silent = false;

        $message = new TelegramMessage($chatId, $text, $silent);

        $this->assertSame($text, $message->text);
    }

    public function testMessageWithNewlines(): void
    {
        $chatId = 666666666;
        $text = "Line 1\nLine 2\nLine 3";
        $silent = false;

        $message = new TelegramMessage($chatId, $text, $silent);

        $this->assertSame($text, $message->text);
        $this->assertStringContainsString("\n", $message->text);
    }

    public function testMessageWithSpecialCharacters(): void
    {
        $chatId = 777777777;
        $text = 'Special chars: !@#$%^&*()_+-=[]{}|;:,.<>?';
        $silent = false;

        $message = new TelegramMessage($chatId, $text, $silent);

        $this->assertSame($text, $message->text);
    }

    public function testMessageWithUrlInText(): void
    {
        $chatId = 888888888;
        $text = 'Check this link: <a href="https://example.com">Example</a>';
        $silent = false;

        $message = new TelegramMessage($chatId, $text, $silent);

        $this->assertStringContainsString('https://example.com', $message->text);
    }
}
