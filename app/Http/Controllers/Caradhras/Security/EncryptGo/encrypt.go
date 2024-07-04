package main

import (
	"crypto/aes"
	"crypto/cipher"
	"crypto/rand"
	"crypto/rsa"
	"crypto/sha256"
	"crypto/x509"
	"encoding/base64"
	"encoding/json"
	"encoding/pem"
	"fmt"
	math "math/rand"
	"time"
)

func main() {

	var (
		charset    = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789"
		seededRand = math.New(math.NewSource(time.Now().UnixNano()))
	)

	pub := "LS0tLS1CRUdJTiBQVUJMSUMgS0VZLS0tLS0KTUlJQklqQU5CZ2txaGtpRzl3MEJBUUVGQUFPQ0FROEFNSUlCQ2dLQ0FRRUEvTmpoOW5aaEQ5UWk2Qi9Rb3dZTgpJcWpra1RQMDNReXJabG5ucDdiRUJmV0h4dkFiRkdMY3pRQ29pVkFaTTQrSnVRZzhrUDRxZkwvb3JLWDA1YkVKCjdWY0lHcXN1bjl3aHN2SnI2YmdaaWxva0NQZGpuU1VCODc0TDRCOW1SWHYycmsrYktSV2VhcllHUHBqZE9MM1AKcVkzRWdVS01Ja2V5aEpvUlZIZkxaTWZ3dFE2R00zd1pPQUE0U2RROC9Gc0JDeEFSdWJPL09YRWR6NEh3Vi84UQpqRE5sOTdGSGd2anhKdWhjRnFVeHkyMHBFMWdNYTgwWmVHOE41bGNpR2RVcld1Q0MzZnp5MU5MM0Q0VzMySGZxCm56SGYwbzhOZ0t0UDJCM0ErbVBrcHJsMk04bGh0Zy9LN2k2bWJQN2dDVXZlMHNvSytHaHlsV3Q3WFczdERXdUMKdndJREFRQUIKLS0tLS1FTkQgUFVCTElDIEtFWS0tLS0tCg=="
	txt := "1234"

	generate := func(l int) []byte {
		b := make([]byte, l)
		for i := range b {
			b[i] = charset[seededRand.Intn(len(charset))]
		}
		return b
	}

	aesRand := generate(32) // 32 bytes = 256 bits
	iv := generate(12)      // 12 bytes == 96 bits

	block, err := aes.NewCipher(aesRand)
	if err != nil {
		panic(err)
	}

	aead, err := cipher.NewGCM(block)
	if err != nil {
		panic(err)
	}

	cipherText := aead.Seal(nil, iv, []byte(txt), nil)

	pubDecoder, err := base64.StdEncoding.DecodeString(pub)
	if err != nil {
		panic(err)
	}

	blockPub, _ := pem.Decode(pubDecoder)
	if blockPub == nil {
		panic(1)
	}

	b := blockPub.Bytes

	ifc, err := x509.ParsePKIXPublicKey(b)
	if err != nil {
		panic(err)
	}

	key, _ := ifc.(*rsa.PublicKey)

	hash := sha256.New()
	aesCipher, err := rsa.EncryptOAEP(hash, rand.Reader, key, aesRand, nil)
	if err != nil {
		panic(err)
	}

	jsonData := map[string]interface{}{
		"aes":     base64.StdEncoding.EncodeToString(aesCipher),
		"iv":      base64.StdEncoding.EncodeToString(iv),
		"encrypt": base64.StdEncoding.EncodeToString(cipherText),
	}

	jsonValue, _ := json.Marshal(jsonData)

	fmt.Println(string(jsonValue))

	// fmt.Printf("KEY:\n %s\n", base64.StdEncoding.EncodeToString(aesCipher))
	// fmt.Printf("IV:\n %s\n", base64.StdEncoding.EncodeToString(iv))
	// fmt.Printf("ENCRYPT:\n %s\n", base64.StdEncoding.EncodeToString(cipherText))

}
