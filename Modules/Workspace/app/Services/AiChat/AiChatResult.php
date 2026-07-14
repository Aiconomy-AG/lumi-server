<?php

namespace Modules\Workspace\Services\AiChat;

class AiChatResult
{
    public function __construct(
        public readonly ?string $text = null,
        public readonly ?ProposedAction $proposedAction = null,
        public readonly ?string $error = null,
    ) {}

    public static function error(string $message): self
    {
        return new self(error: $message);
    }

    public function hasProposedAction(): bool
    {
        return $this->proposedAction !== null;
    }

    public function hasError(): bool
    {
        return $this->error !== null;
    }

    public function replyText(): ?string
    {
        if ($this->error !== null) {
            return '[Lumi AI error] '.$this->error;
        }

        return $this->text;
    }
}
