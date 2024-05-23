package main

import (
	"crypto/aes"
	"crypto/cipher"
	"crypto/rand"
	"crypto/rsa"
	"crypto/sha256"
	"crypto/x509"
	"encoding/base64"
	"encoding/pem"
	"fmt"
	"os"
)

func main() {
	// if len(os.Args) < 5 {
	// 	fmt.Println("Usage: <program> <pkBase64> <aesBase64> <ivBase64> <msgBase64>")
	// 	return
	// }

	pkBase64 := os.Args[1]
	aesBase64 := os.Args[2]
	ivBase64 := os.Args[3]
	msgBase64 := os.Args[4]

	// Decodificar la clave privada en formato base64
	pk, err := base64.StdEncoding.DecodeString(pkBase64)
	if err != nil {
		panic(err)
	}

	// Decodificar el bloque PEM
	blockPEM, _ := pem.Decode(pk)
	if blockPEM == nil {
		panic("Failed to decode PEM block containing the key")
	}

	// Parsear la clave privada
	privateKey, err := x509.ParsePKCS8PrivateKey(blockPEM.Bytes)
	if err != nil {
		panic(err)
	}

	pkPEM := privateKey.(*rsa.PrivateKey)

	// Decodificar la clave AES en formato base64
	aesKey, err := base64.StdEncoding.DecodeString(aesBase64)
	if err != nil {
		panic(err)
	}

	// Desencriptar la clave AES usando RSA-OAEP
	hash := sha256.New()
	key, err := rsa.DecryptOAEP(hash, rand.Reader, pkPEM, aesKey, nil)
	if err != nil {
		panic(err)
	}

	// Decodificar el IV en formato base64
	iv, err := base64.StdEncoding.DecodeString(ivBase64)
	if err != nil {
		panic(err)
	}

	if len(iv) != aes.BlockSize {
		panic(fmt.Sprintf("Incorrect IV length: expected %d bytes, got %d", aes.BlockSize, len(iv)))
	}

	// Decodificar el mensaje en formato base64
	msg, err := base64.StdEncoding.DecodeString(msgBase64)
	if err != nil {
		panic(err)
	}

	// Crear el cifrador AES
	block, err := aes.NewCipher(key)
	if err != nil {
		panic(err)
	}

	// Crear el modo CBC de descifrado
	mode := cipher.NewCBCDecrypter(block, iv)

	// Crear el buffer para el texto desencriptado
	decrypted := make([]byte, len(msg))

	// Desencriptar el mensaje
	mode.CryptBlocks(decrypted, msg)

	// Eliminar el padding PKCS7
	decrypted, err = pkcs7Unpadding(decrypted, aes.BlockSize)
	if err != nil {
		panic(err)
	}

	fmt.Println(string(decrypted))
}

// pkcs7Unpadding elimina el padding PKCS7
func pkcs7Unpadding(plaintext []byte, blockSize int) ([]byte, error) {
	length := len(plaintext)
	if length == 0 {
		return nil, fmt.Errorf("plaintext is empty")
	}
	if length%blockSize != 0 {
		return nil, fmt.Errorf("plaintext is not a multiple of the block size")
	}

	padding := int(plaintext[length-1])
	if padding > blockSize || padding == 0 {
		return nil, fmt.Errorf("invalid padding size")
	}

	for _, v := range plaintext[length-padding:] {
		if int(v) != padding {
			return nil, fmt.Errorf("invalid padding byte")
		}
	}

	return plaintext[:length-padding], nil
}
