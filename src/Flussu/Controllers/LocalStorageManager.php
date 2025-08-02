<?php
// File: /src/Flussu/Controllers/LocalStorageManager.php

namespace Flussu\Controllers;

use Flussu\General;

class LocalStorageManager
{
    protected string $baseDir;
    protected string $uploadDir;
    protected string $tempDir;
    
    public function __construct()
    {
        // Determina la directory base del server
        $this->baseDir = $this->determineBaseDir();
        $this->uploadDir = $this->baseDir . '/Uploads';
        $this->tempDir = $this->uploadDir . '/temp';
        
        // Inizializza le directory locali
        $this->initializeLocalDirectories();
    }
    
    /**
     * Determina la directory base del progetto
     */
    private function determineBaseDir(): string
    {
        // Prova diversi metodi per trovare la root del progetto
        if (isset($_SERVER['DOCUMENT_ROOT']) && !empty($_SERVER['DOCUMENT_ROOT'])) {
            return $_SERVER['DOCUMENT_ROOT'];
        }
        
        // Fallback: usa la directory corrente risalendo dalla posizione di questo file
        // Assumendo che siamo in /src/Flussu/Controllers/
        return dirname(__DIR__, 3); // Risale di 3 livelli
    }
    
    /**
     * Inizializza le directory locali sul server
     */
    private function initializeLocalDirectories(): void
    {
        // Crea directory Uploads se non esiste
        if (!is_dir($this->uploadDir)) {
            if (!mkdir($this->uploadDir, 0755, true)) {
                throw new \Exception("Impossibile creare la directory Uploads: " . $this->uploadDir);
            }
            General::addRowLog("Creata directory locale: " . $this->uploadDir);
        }
        
        // Crea directory temp se non esiste
        if (!is_dir($this->tempDir)) {
            if (!mkdir($this->tempDir, 0755, true)) {
                throw new \Exception("Impossibile creare la directory temp: " . $this->tempDir);
            }
            General::addRowLog("Creata directory locale: " . $this->tempDir);
        }
    }
    
    /**
     * Salva contenuto in un file locale
     * 
     * @param string $content Contenuto da salvare
     * @param string $filename Nome del file
     * @param bool $isTemp Se true, salva in temp, altrimenti in Uploads
     * @return string Path completo del file salvato
     */
    public function saveLocalFile(string $content, string $filename, bool $isTemp = false): string
    {
        $directory = $isTemp ? $this->tempDir : $this->uploadDir;
        $filepath = $directory . '/' . $this->sanitizeFilename($filename);
        
        if (file_put_contents($filepath, $content) === false) {
            throw new \Exception("Impossibile salvare il file locale: " . $filepath);
        }
        
        General::addRowLog("File salvato localmente: " . $filepath);
        return $filepath;
    }
    
    /**
     * Legge un file locale
     * 
     * @param string $filepath Path del file
     * @return string Contenuto del file
     */
    public function readLocalFile(string $filepath): string
    {
        if (!file_exists($filepath)) {
            throw new \Exception("File locale non trovato: " . $filepath);
        }
        
        $content = file_get_contents($filepath);
        if ($content === false) {
            throw new \Exception("Impossibile leggere il file locale: " . $filepath);
        }
        
        return $content;
    }
    
    /**
     * Genera un percorso file temporaneo unico
     * 
     * @param string $prefix Prefisso del nome file
     * @param string $extension Estensione del file
     * @return string Path completo
     */
    public function generateTempFilePath(string $prefix = 'temp', string $extension = 'tmp'): string
    {
        $filename = $prefix . '_' . uniqid() . '_' . time() . '.' . $extension;
        return $this->tempDir . '/' . $filename;
    }
    
    /**
     * Elimina un file locale
     * 
     * @param string $filepath Path del file
     * @return bool
     */
    public function deleteLocalFile(string $filepath): bool
    {
        if (!file_exists($filepath)) {
            return true; // Già eliminato
        }
        
        if (unlink($filepath)) {
            General::addRowLog("File locale eliminato: " . $filepath);
            return true;
        }
        
        return false;
    }
    
    /**
     * Lista file in una directory locale
     * 
     * @param bool $fromTemp Se true lista da temp, altrimenti da Uploads
     * @param string $pattern Pattern per filtrare i file (es. "*.txt")
     * @return array
     */
    public function listLocalFiles(bool $fromTemp = false, string $pattern = '*'): array
    {
        $directory = $fromTemp ? $this->tempDir : $this->uploadDir;
        $files = glob($directory . '/' . $pattern);
        
        $result = [];
        foreach ($files as $file) {
            if (is_file($file)) {
                $result[] = [
                    'path' => $file,
                    'name' => basename($file),
                    'size' => filesize($file),
                    'modified' => filemtime($file)
                ];
            }
        }
        
        return $result;
    }
    
    /**
     * Pulisce i file temporanei più vecchi di X giorni
     * 
     * @param int $days Giorni di retention
     * @return int Numero di file eliminati
     */
    public function cleanupTempFiles(int $days = 7): int
    {
        $deleted = 0;
        $cutoffTime = time() - ($days * 24 * 60 * 60);
        
        $files = $this->listLocalFiles(true);
        foreach ($files as $file) {
            if ($file['modified'] < $cutoffTime) {
                if ($this->deleteLocalFile($file['path'])) {
                    $deleted++;
                }
            }
        }
        
        General::addRowLog("Pulizia temp: eliminati $deleted file più vecchi di $days giorni");
        return $deleted;
    }
    
    /**
     * Ottiene il percorso della directory Uploads
     */
    public function getUploadDir(): string
    {
        return $this->uploadDir;
    }
    
    /**
     * Ottiene il percorso della directory temp
     */
    public function getTempDir(): string
    {
        return $this->tempDir;
    }
    
    /**
     * Sanitizza il nome del file
     */
    private function sanitizeFilename(string $filename): string
    {
        // Rimuove caratteri pericolosi
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        // Evita nomi riservati
        $filename = str_replace(['..', './', '/.'], '_', $filename);
        return $filename;
    }
}