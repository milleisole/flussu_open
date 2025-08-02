<?php
// File: /src/Flussu/Contracts/ICloudStorageProvider.php

namespace Flussu\Contracts;

interface ICloudStorageProvider
{
    /**
     * Elenca i file in una cartella
     * 
     * @param string|null $folderId ID/path della cartella, null per root
     * @param array $options Opzioni aggiuntive (pageSize, orderBy, etc.)
     * @return array Lista dei file con struttura standardizzata
     */
    public function listFiles(?string $folderId = null, array $options = []): array;
    
    /**
     * Cerca file per nome
     * 
     * @param string $query Query di ricerca
     * @param string|null $folderId Limita la ricerca a una cartella specifica
     * @param array $options Opzioni aggiuntive
     * @return array Lista dei file trovati
     */
    public function searchFiles(string $query, ?string $folderId = null, array $options = []): array;
    
    /**
     * Ottiene i metadati di un file
     * 
     * @param string $fileId ID del file
     * @return array Metadati del file in formato standardizzato
     */
    public function getFileMetadata(string $fileId): array;
    
    /**
     * Verifica se un file esiste
     * 
     * @param string $fileId ID del file
     * @return bool
     */
    public function fileExists(string $fileId): bool;
    
    /**
     * Crea una nuova cartella
     * 
     * @param string $folderName Nome della cartella
     * @param string|null $parentFolderId ID della cartella parent, null per root
     * @return array Metadati della cartella creata
     */
    public function createFolder(string $folderName, ?string $parentFolderId = null): array;
    
    /**
     * Scarica il contenuto di un file
     * 
     * @param string $fileId ID del file
     * @return string Contenuto del file
     */
    public function downloadFile(string $fileId): string;
    
    /**
     * Carica un nuovo file
     * 
     * @param string $fileName Nome del file
     * @param string $content Contenuto del file
     * @param string|null $folderId ID della cartella di destinazione
     * @param string|null $mimeType MIME type del file
     * @return array Metadati del file caricato
     */
    public function uploadFile(string $fileName, string $content, ?string $folderId = null, ?string $mimeType = null): array;
    
    /**
     * Aggiorna il contenuto di un file esistente
     * 
     * @param string $fileId ID del file
     * @param string $content Nuovo contenuto
     * @return array Metadati del file aggiornato
     */
    public function updateFile(string $fileId, string $content): array;
    
    /**
     * Elimina un file o cartella
     * 
     * @param string $fileId ID del file/cartella
     * @return bool
     */
    public function deleteFile(string $fileId): bool;
    
    /**
     * Sposta un file in un'altra cartella
     * 
     * @param string $fileId ID del file
     * @param string $newParentId ID della nuova cartella parent
     * @return array Metadati del file spostato
     */
    public function moveFile(string $fileId, string $newParentId): array;
    
    /**
     * Copia un file
     * 
     * @param string $fileId ID del file da copiare
     * @param string|null $newName Nuovo nome (opzionale)
     * @param string|null $folderId Cartella di destinazione (opzionale)
     * @return array Metadati del file copiato
     */
    public function copyFile(string $fileId, ?string $newName = null, ?string $folderId = null): array;
    
    /**
     * Ottiene un link condivisibile per il file
     * 
     * @param string $fileId ID del file
     * @param array $options Opzioni di condivisione
     * @return string URL condivisibile
     */
    public function getShareableLink(string $fileId, array $options = []): string;
    
    /**
     * Ottiene informazioni sullo spazio di archiviazione
     * 
     * @return array ['used' => bytes, 'total' => bytes, 'free' => bytes]
     */
    public function getStorageInfo(): array;
}