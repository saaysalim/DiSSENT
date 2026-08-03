package chaincode

import (
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"fmt"
	"time"

	"github.com/hyperledger/fabric-contract-api-go/v2/contractapi"
)

// SmartContract implements the DiSSENT ledger: a tamper-evident hash chain of
// report verifications, and a public-key registry used to authenticate
// cross-border secure messages.
type SmartContract struct {
	contractapi.Contract
}

const (
	verificationKeyPrefix = "VERIFICATION_"
	chainBlockKeyPrefix   = "CHAIN_BLOCK_"
	chainHeadKey          = "CHAIN_HEAD"
	pubKeyPrefix          = "PUBKEY_"
	genesisHash           = "0"
)

// VerificationRecord is one block in the report verification hash chain.
type VerificationRecord struct {
	ReportID   string `json:"ReportID"`
	Title      string `json:"Title"`
	DataHash   string `json:"DataHash"`
	PrevHash   string `json:"PrevHash"`
	BlockHash  string `json:"BlockHash"`
	BlockIndex int    `json:"BlockIndex"`
	Timestamp  string `json:"Timestamp"`
}

// ChainHead tracks the tip of the hash chain so new blocks link to the last one.
type ChainHead struct {
	LastBlockHash string `json:"LastBlockHash"`
	BlockCount    int    `json:"BlockCount"`
}

// PublicKeyRecord is a registered organisation/user public key used to verify
// the signature on secure messages exchanged between securecomm and securecomm2.
type PublicKeyRecord struct {
	UserID       string `json:"UserID"`
	OrgID        string `json:"OrgID"`
	PublicKeyPEM string `json:"PublicKeyPEM"`
	RegisteredAt string `json:"RegisteredAt"`
}

// InitLedger sets up the genesis chain head. Safe to call once on channel setup.
func (s *SmartContract) InitLedger(ctx contractapi.TransactionContextInterface) error {
	existing, err := ctx.GetStub().GetState(chainHeadKey)
	if err != nil {
		return fmt.Errorf("failed to read chain head: %v", err)
	}
	if existing != nil {
		return nil // already initialised
	}

	head := ChainHead{LastBlockHash: genesisHash, BlockCount: 0}
	headJSON, err := json.Marshal(head)
	if err != nil {
		return err
	}
	return ctx.GetStub().PutState(chainHeadKey, headJSON)
}

func (s *SmartContract) getChainHead(ctx contractapi.TransactionContextInterface) (ChainHead, error) {
	headJSON, err := ctx.GetStub().GetState(chainHeadKey)
	if err != nil {
		return ChainHead{}, fmt.Errorf("failed to read chain head: %v", err)
	}
	if headJSON == nil {
		return ChainHead{LastBlockHash: genesisHash, BlockCount: 0}, nil
	}

	var head ChainHead
	if err := json.Unmarshal(headJSON, &head); err != nil {
		return ChainHead{}, err
	}
	return head, nil
}

func (s *SmartContract) txTimestamp(ctx contractapi.TransactionContextInterface) (string, error) {
	ts, err := ctx.GetStub().GetTxTimestamp()
	if err != nil {
		return "", fmt.Errorf("failed to read tx timestamp: %v", err)
	}
	return time.Unix(ts.Seconds, int64(ts.Nanos)).UTC().Format(time.RFC3339), nil
}

// AddVerification appends a new block to the hash chain for the given report,
// linking it to the previous block's hash, and returns the new block hash.
func (s *SmartContract) AddVerification(ctx contractapi.TransactionContextInterface, reportID string, title string, dataHash string) (string, error) {
	if reportID == "" || dataHash == "" {
		return "", fmt.Errorf("reportID and dataHash must not be empty")
	}

	exists, err := s.verificationExists(ctx, reportID)
	if err != nil {
		return "", err
	}
	if exists {
		return "", fmt.Errorf("report %s has already been verified", reportID)
	}

	head, err := s.getChainHead(ctx)
	if err != nil {
		return "", err
	}

	sum := sha256.Sum256([]byte(head.LastBlockHash + dataHash))
	blockHash := hex.EncodeToString(sum[:])

	timestamp, err := s.txTimestamp(ctx)
	if err != nil {
		return "", err
	}

	record := VerificationRecord{
		ReportID:   reportID,
		Title:      title,
		DataHash:   dataHash,
		PrevHash:   head.LastBlockHash,
		BlockHash:  blockHash,
		BlockIndex: head.BlockCount,
		Timestamp:  timestamp,
	}

	recordJSON, err := json.Marshal(record)
	if err != nil {
		return "", err
	}
	if err := ctx.GetStub().PutState(verificationKeyPrefix+reportID, recordJSON); err != nil {
		return "", fmt.Errorf("failed to store verification record: %v", err)
	}

	blockKey := fmt.Sprintf("%s%010d", chainBlockKeyPrefix, head.BlockCount)
	if err := ctx.GetStub().PutState(blockKey, []byte(reportID)); err != nil {
		return "", fmt.Errorf("failed to store chain index: %v", err)
	}

	newHead := ChainHead{LastBlockHash: blockHash, BlockCount: head.BlockCount + 1}
	newHeadJSON, err := json.Marshal(newHead)
	if err != nil {
		return "", err
	}
	if err := ctx.GetStub().PutState(chainHeadKey, newHeadJSON); err != nil {
		return "", fmt.Errorf("failed to update chain head: %v", err)
	}

	return blockHash, nil
}

func (s *SmartContract) verificationExists(ctx contractapi.TransactionContextInterface, reportID string) (bool, error) {
	recordJSON, err := ctx.GetStub().GetState(verificationKeyPrefix + reportID)
	if err != nil {
		return false, fmt.Errorf("failed to read from world state: %v", err)
	}
	return recordJSON != nil, nil
}

// GetVerification returns the verification record for a single report.
func (s *SmartContract) GetVerification(ctx contractapi.TransactionContextInterface, reportID string) (*VerificationRecord, error) {
	recordJSON, err := ctx.GetStub().GetState(verificationKeyPrefix + reportID)
	if err != nil {
		return nil, fmt.Errorf("failed to read from world state: %v", err)
	}
	if recordJSON == nil {
		return nil, fmt.Errorf("no verification record for report %s", reportID)
	}

	var record VerificationRecord
	if err := json.Unmarshal(recordJSON, &record); err != nil {
		return nil, err
	}
	return &record, nil
}

// GetAllVerifications returns every block in the chain, oldest first.
func (s *SmartContract) GetAllVerifications(ctx contractapi.TransactionContextInterface) ([]*VerificationRecord, error) {
	iterator, err := ctx.GetStub().GetStateByRange(chainBlockKeyPrefix, chainBlockKeyPrefix+"~")
	if err != nil {
		return nil, err
	}
	defer iterator.Close()

	// Must be a non-nil empty slice, not a nil one: encoding/json serializes nil as
	// JSON null, and the Java client's history endpoint calls .size() on the decoded
	// list without a null check — an empty chain would NPE it instead of returning [].
	records := make([]*VerificationRecord, 0)
	for iterator.HasNext() {
		entry, err := iterator.Next()
		if err != nil {
			return nil, err
		}

		reportID := string(entry.Value)
		record, err := s.GetVerification(ctx, reportID)
		if err != nil {
			return nil, err
		}
		records = append(records, record)
	}

	return records, nil
}

// VerifyChainIntegrity walks every block and confirms BlockHash still equals
// sha256(PrevHash + DataHash) — the same tamper-evident check the PHP side used
// to run against MySQL, now run against the ledger.
func (s *SmartContract) VerifyChainIntegrity(ctx contractapi.TransactionContextInterface) (bool, error) {
	records, err := s.GetAllVerifications(ctx)
	if err != nil {
		return false, err
	}

	for _, record := range records {
		sum := sha256.Sum256([]byte(record.PrevHash + record.DataHash))
		expected := hex.EncodeToString(sum[:])
		if expected != record.BlockHash {
			return false, nil
		}
	}

	return true, nil
}

// RegisterPublicKey stores (or replaces) the public key used to verify signed
// messages from a given user/organisation.
func (s *SmartContract) RegisterPublicKey(ctx contractapi.TransactionContextInterface, userID string, orgID string, publicKeyPEM string) error {
	if userID == "" || publicKeyPEM == "" {
		return fmt.Errorf("userID and publicKeyPEM must not be empty")
	}

	timestamp, err := s.txTimestamp(ctx)
	if err != nil {
		return err
	}

	record := PublicKeyRecord{
		UserID:       userID,
		OrgID:        orgID,
		PublicKeyPEM: publicKeyPEM,
		RegisteredAt: timestamp,
	}

	recordJSON, err := json.Marshal(record)
	if err != nil {
		return err
	}

	return ctx.GetStub().PutState(pubKeyPrefix+userID, recordJSON)
}

// GetPublicKey looks up a previously registered public key by user ID.
func (s *SmartContract) GetPublicKey(ctx contractapi.TransactionContextInterface, userID string) (*PublicKeyRecord, error) {
	recordJSON, err := ctx.GetStub().GetState(pubKeyPrefix + userID)
	if err != nil {
		return nil, fmt.Errorf("failed to get public key for %s: %v", userID, err)
	}
	if recordJSON == nil {
		return nil, fmt.Errorf("no public key registered for %s", userID)
	}

	var record PublicKeyRecord
	if err := json.Unmarshal(recordJSON, &record); err != nil {
		return nil, err
	}
	return &record, nil
}
