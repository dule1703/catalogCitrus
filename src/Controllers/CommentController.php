<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use App\Services\CommentService;
use Psr\Log\LoggerInterface;
use Nyholm\Psr7\Response;

class CommentController
{
    private CommentService $commentService;  
    private LoggerInterface $logger;

    public function __construct(
        CommentService $commentService,       
        LoggerInterface $logger
    ) {
        $this->commentService = $commentService;      
        $this->logger         = $logger;
    }

    public function store(ServerRequestInterface $request): ResponseInterface
    {
        $data = $this->getFormData($request) ?? [];

        try {
            $this->commentService->addComment($data);

            return new Response(302, [
                'Location' => '/?success=comment_added'
            ]);

        } catch (\InvalidArgumentException $e) {
            // Validacione greške iz CommentService
            return new Response(302, [
                'Location' => '/?error=' . urlencode($e->getMessage())
            ]);

        } catch (\Throwable $e) {           // ← bolje od \Exception
            $this->logger->error('Neočekivana greška pri dodavanju komentara', [
                'exception' => $e,
                'data'      => $data
            ]);

            return new Response(302, [
                'Location' => '/?error=Došlo+je+do+neočekivane+greške.+Pokušajte+ponovo.'
            ]);
        }
    }   

    private function getFormData(ServerRequestInterface $request): array
    {
        $body = $request->getParsedBody();
        return is_array($body) ? $body : [];
    }
}