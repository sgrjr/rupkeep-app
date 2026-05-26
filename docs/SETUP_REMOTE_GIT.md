# Setting Up Git on Remote Server

## Option 1: Switch to SSH (Recommended)

### Step 1: Check current remote URL
```bash
git remote -v
```

### Step 2: Change to SSH URL
```bash
git remote set-url origin git@github.com:sgrjr/rupkeep-app.git
```

### Step 3: Set up SSH key (if not already done)

1. Generate SSH key (if you don't have one):
```bash
ssh-keygen -t ed25519 -C "your_email@example.com"
# Press Enter to accept default location
# Optionally set a passphrase
```

2. Display your public key:
```bash
cat ~/.ssh/id_ed25519.pub
```

3. Add the SSH key to GitHub:
   - Go to GitHub.com → Settings → SSH and GPG keys
   - Click "New SSH key"
   - Paste the public key content
   - Save

4. Test the connection:
```bash
ssh -T git@github.com
```

### Step 4: Try pushing again
```bash
git push
```

---

## Option 2: Use HTTPS with Personal Access Token

If you prefer to stick with HTTPS:

### Step 1: Create a Personal Access Token on GitHub
1. Go to GitHub.com → Settings → Developer settings → Personal access tokens → Tokens (classic)
2. Click "Generate new token (classic)"
3. Give it a name (e.g., "rupkeep-server")
4. Select scopes: `repo` (full control of private repositories)
5. Generate and **copy the token** (you won't see it again!)

### Step 2: Update remote URL (if needed)
```bash
git remote set-url origin https://github.com/sgrjr/rupkeep-app.git
```

### Step 3: Configure Git credential helper
```bash
git config --global credential.helper store
```

### Step 4: Push (will prompt for credentials)
```bash
git push
# Username: sgrjr
# Password: [paste your personal access token here]
```

The credentials will be stored for future use.

---

## Troubleshooting

### If SSH key already exists but isn't working:
```bash
# Check if SSH agent is running
eval "$(ssh-agent -s)"

# Add your SSH key
ssh-add ~/.ssh/id_ed25519

# Test connection
ssh -T git@github.com
```

### If you get "Permission denied (publickey)":
- Make sure your SSH public key is added to your GitHub account
- Verify the key is in `~/.ssh/` directory
- Check file permissions: `chmod 600 ~/.ssh/id_ed25519`
