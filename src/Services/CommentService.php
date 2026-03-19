<?php

namespace App\Services;

use App\Repositories\CommentRepository;
use Psr\Log\LoggerInterface;
use InvalidArgumentException;
use App\Services\InputValidator;

class CommentService {

    private CommentRepository $repository;
    private LoggerInterface $logger;

    public function __construct(CommentRepository $repository, LoggerInterface $logger) {
        $this->repository = $repository;
        $this->logger = $logger;
    }

   public function addComment(array $data): bool 
    {
        $name = InputValidator::sanitizeString($data['name'] ?? '');
        if (empty($name) || strlen($name) < 3) {
            throw new InvalidArgumentException('Ime mora imati najmanje 3 karaktera.');
        }

        // Email validacija - hvata grešku ako je email neispravan
        try {
            $email = InputValidator::validateEmail($data['email'] ?? '');
        } catch (\InvalidArgumentException $e) {
            throw new InvalidArgumentException('Unesite validnu email adresu.');
        }

        $text = InputValidator::sanitizeString($data['text'] ?? '');
        if (empty($text) || strlen($text) < 10) {
            throw new InvalidArgumentException('Komentar mora imati najmanje 10 karaktera.');
        }

        $success = $this->repository->create([
            'name'  => $name,
            'email' => $email,
            'text'  => $text
        ]);

        if ($success) {
            $this->logger->info("Novi komentar dodat od: {$name}");
        }

        return $success;
    }
}