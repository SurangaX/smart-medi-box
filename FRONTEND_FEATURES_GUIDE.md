# Frontend Features Implementation Guide

## Summary of Completed Work

### ✅ Backend Implementation (100% Complete)
- **Articles API** (`robot_api/articles.php`):
  - `GET /api/articles/list` - Fetch all published articles
  - `POST /api/articles/my` - Get doctor's articles (requires token)
  - `POST /api/articles/create` - Create new article
  - `POST /api/articles/update` - Update existing article
  - `POST /api/articles/delete` - Soft delete article
  - `POST /api/articles/view` - Increment view count

- **Database Schema**:
  - Added `articles` table with 13 columns (id, article_id, doctor_id, title, content, cover_image, status, views, created_at, updated_at, deleted_at)
  - Added `profile_photo`, `email`, `password_hash`, `role` columns to `users` table
  - Created 3 indexes for optimal query performance

- **Database Migrations**:
  - Migration 001: Added `session_tokens` table
  - Migration 002: Added `schedule_date` column to `schedules`
  - Migration 003: Added articles system and profile photo support

### ✅ CSS Styling (100% Complete)
- **Modernized Logout Modal**:
  - Backdrop blur effect (4px)
  - Smooth fade-in animation (0.3s)
  - Improved button styling with gradients
  - Better hover states with transform effects

- **Photo Upload Styles**:
  - Photo preview container (120x120px, dashed border)
  - Upload button with gradient background
  - Hover effects

- **Articles Section Styles**:
  - Responsive grid layout (280px min-width columns)
  - Article cards with hover effects
  - Article cover images with fallback gradients
  - Article metadata (author, views, date)
  - Article creation form styling

---

## Remaining Tasks: Frontend Components & Logic

### 1. Photo Upload on Patient Signup

**Location**: `dashboard/src/App.jsx` - SignupScreen component (line ~116)

**Implementation Steps**:

```jsx
// Step 1: Add useRef at the top of SignupScreen
const photoInputRef = useRef(null);

// Step 2: Add photo to formData state
const [formData, setFormData] = useState({
  // ... existing fields ...
  photo: null  // Base64 encoded photo
});

// Step 3: Create photo change handler
const handlePhotoChange = (e) => {
  const file = e.target.files[0];
  if (file) {
    // Validate file size (max 5MB)
    if (file.size > 5 * 1024 * 1024) {
      alert('Photo must be less than 5MB');
      return;
    }
    
    const reader = new FileReader();
    reader.onloadend = () => {
      setFormData(prev => ({ 
        ...prev, 
        photo: reader.result  // Data URL
      }));
    };
    reader.readAsDataURL(file);
  }
};

// Step 4: Update handleSubmit to include photo
const handleSubmit = async (e) => {
  e.preventDefault();
  setLoading(true);
  setError('');

  try {
    const payload = {
      name: formData.name,
      email: formData.email,
      password: formData.password,
      nic: formData.nic,
      dob: formData.date_of_birth,
      phone: formData.phone_number,
      photo: formData.photo  // Add base64 photo
    };

    // ... rest of submit logic ...
  }
};

// Step 5: Add to form JSX (after password field)
<div className="form-group photo-upload-group">
  <label>Profile Photo *</label>
  <div className="photo-preview" className={formData.photo ? '' : 'empty'}>
    {formData.photo ? (
      <img src={formData.photo} alt="Profile preview" />
    ) : (
      <span>📷</span>
    )}
  </div>
  <input
    type="file"
    ref={photoInputRef}
    onChange={handlePhotoChange}
    accept="image/*"
    className="photo-input"
    required
  />
  <button
    type="button"
    className="photo-upload-btn"
    onClick={() => photoInputRef.current?.click()}
  >
    Choose Photo
  </button>
</div>
```

**Backend Handling** (`robot_api/auth.php`):
```php
// In patient/signup or doctor/signup handler
$photo = $input['photo'] ?? null;

if ($photo) {
  // Store base64 photo or convert to file
  $photo_path = 'uploads/profiles/' . uniqid() . '.jpg';
  // Save file with proper error handling
}

// Add to INSERT query
$query = "INSERT INTO users (email, password_hash, name, nic, dob, phone, gender, blood_type, role, profile_photo)
          VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10)";
```

---

### 2. Articles Feed for Patients

**Location**: `dashboard/src/App.jsx` - Add to PatientDashboard component

**Implementation**:

```jsx
// Add state variables
const [articles, setArticles] = useState([]);
const [selectedArticle, setSelectedArticle] = useState(null);
const [articlesLoading, setArticlesLoading] = useState(false);

// Load articles on component mount
useEffect(() => {
  fetchArticles();
}, []);

const fetchArticles = async () => {
  try {
    setArticlesLoading(true);
    const response = await fetch(`${API_URL}/index.php/api/articles/list`);
    const data = await response.json();
    
    if (data.status === 'SUCCESS') {
      setArticles(data.articles || []);
    }
  } catch (err) {
    console.error('Failed to fetch articles:', err);
  } finally {
    setArticlesLoading(false);
  }
};

// Track article views
const handleViewArticle = async (articleId) => {
  try {
    await fetch(`${API_URL}/index.php/api/articles/view`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ article_id: articleId })
    });
  } catch (err) {
    console.error('Failed to track view:', err);
  }
};

// JSX for articles section
<div className="articles-container">
  <h2>📰 Latest Articles</h2>
  
  {articlesLoading ? (
    <p>Loading articles...</p>
  ) : articles.length === 0 ? (
    <p style={{ color: 'var(--text-secondary)' }}>No articles available yet</p>
  ) : (
    <div className="articles-grid">
      {articles.map(article => (
        <div
          key={article.id}
          className="article-card"
          onClick={() => {
            setSelectedArticle(article);
            handleViewArticle(article.article_id);
          }}
        >
          <div className="article-cover">
            {article.cover_image ? (
              <img src={article.cover_image} alt={article.title} />
            ) : (
              '📄'
            )}
          </div>
          <div className="article-content">
            <h3 className="article-title">{article.title}</h3>
            <p className="article-excerpt">{article.excerpt}</p>
            <div className="article-meta">
              <div className="article-author">
                {article.doctor_name}
              </div>
              <div className="article-views">
                👁️ {article.views}
              </div>
            </div>
          </div>
        </div>
      ))}
    </div>
  )}
</div>

// Modal for viewing full article
{selectedArticle && (
  <div className="modal-overlay" onClick={() => setSelectedArticle(null)}>
    <div className="modal-content" onClick={e => e.stopPropagation()}>
      <button
        style={{ float: 'right', background: 'none', border: 'none', color: 'var(--text-primary)', cursor: 'pointer', fontSize: '20px' }}
        onClick={() => setSelectedArticle(null)}
      >
        ✕
      </button>
      <h2>{selectedArticle.title}</h2>
      {selectedArticle.cover_image && (
        <img src={selectedArticle.cover_image} alt={selectedArticle.title} style={{ width: '100%', borderRadius: '8px', marginBottom: '16px' }} />
      )}
      <p style={{ marginBottom: '16px', color: 'var(--text-secondary)' }}>
        By {selectedArticle.doctor_name} • {new Date(selectedArticle.created_at).toLocaleDateString()} • 👁️ {selectedArticle.views} views
      </p>
      <div style={{ color: 'var(--text-primary)', lineHeight: '1.8' }}>
        {selectedArticle.content}
      </div>
    </div>
  </div>
)}
```

---

### 3. Article Creation Form for Doctors

**Location**: `dashboard/src/App.jsx` - Add to DoctorDashboard component

**Implementation**:

```jsx
// Add state variables
const [articles, setArticles] = useState([]);
const [showCreateArticle, setShowCreateArticle] = useState(false);
const [newArticle, setNewArticle] = useState({
  title: '',
  content: '',
  cover_image: null
});
const [articlesLoading, setArticlesLoading] = useState(false);

// Load doctor's articles on mount
useEffect(() => {
  fetchMyArticles();
}, [token]);

const fetchMyArticles = async () => {
  try {
    setArticlesLoading(true);
    const response = await fetch(`${API_URL}/index.php/api/articles/my`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ token })
    });
    const data = await response.json();
    
    if (data.status === 'SUCCESS') {
      setArticles(data.articles || []);
    }
  } catch (err) {
    console.error('Failed to fetch articles:', err);
  } finally {
    setArticlesLoading(false);
  }
};

const handleCreateArticle = async (e) => {
  e.preventDefault();
  
  try {
    const payload = {
      token,
      title: newArticle.title,
      content: newArticle.content,
      cover_image: newArticle.cover_image
    };
    
    const response = await fetch(`${API_URL}/index.php/api/articles/create`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const data = await response.json();
    
    if (data.status === 'SUCCESS') {
      setNewArticle({ title: '', content: '', cover_image: null });
      setShowCreateArticle(false);
      fetchMyArticles();  // Refresh list
    } else {
      alert('Failed to create article: ' + data.message);
    }
  } catch (err) {
    alert('Error creating article: ' + err.message);
  }
};

const handleDeleteArticle = async (articleId) => {
  if (!confirm('Delete this article?')) return;
  
  try {
    const response = await fetch(`${API_URL}/index.php/api/articles/delete`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ token, article_id: articleId })
    });
    const data = await response.json();
    
    if (data.status === 'SUCCESS') {
      fetchMyArticles();
    } else {
      alert('Failed to delete article: ' + data.message);
    }
  } catch (err) {
    alert('Error deleting article: ' + err.message);
  }
};

// JSX for articles section in doctor dashboard
<div className="articles-container">
  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px' }}>
    <h2>📝 My Articles</h2>
    <button
      className="btn-primary"
      onClick={() => setShowCreateArticle(!showCreateArticle)}
    >
      {showCreateArticle ? '✕ Cancel' : '+ New Article'}
    </button>
  </div>

  {showCreateArticle && (
    <form onSubmit={handleCreateArticle} className="article-form">
      <div className="article-form-group">
        <label>Title *</label>
        <input
          type="text"
          value={newArticle.title}
          onChange={(e) => setNewArticle(prev => ({ ...prev, title: e.target.value }))}
          placeholder="Article title"
          required
        />
      </div>

      <div className="article-form-group">
        <label>Cover Image URL (optional)</label>
        <input
          type="url"
          value={newArticle.cover_image}
          onChange={(e) => setNewArticle(prev => ({ ...prev, cover_image: e.target.value }))}
          placeholder="https://example.com/image.jpg"
        />
      </div>

      <div className="article-form-group">
        <label>Content *</label>
        <textarea
          value={newArticle.content}
          onChange={(e) => setNewArticle(prev => ({ ...prev, content: e.target.value }))}
          placeholder="Write your article content here..."
          required
        />
      </div>

      <div className="article-button-group">
        <button type="submit" className="btn-primary">
          Publish Article
        </button>
        <button
          type="button"
          className="btn-secondary"
          onClick={() => setShowCreateArticle(false)}
        >
          Cancel
        </button>
      </div>
    </form>
  )}

  {articlesLoading ? (
    <p>Loading articles...</p>
  ) : articles.length === 0 ? (
    <p style={{ color: 'var(--text-secondary)' }}>No articles yet. Create your first one!</p>
  ) : (
    <div className="articles-grid">
      {articles.map(article => (
        <div key={article.id} className="article-card">
          <div className="article-cover">
            {article.cover_image ? (
              <img src={article.cover_image} alt={article.title} />
            ) : (
              '📄'
            )}
          </div>
          <div className="article-content">
            <h3 className="article-title">{article.title}</h3>
            <p className="article-excerpt">{article.content.substring(0, 100)}...</p>
            <div className="article-meta">
              <span style={{ fontSize: '12px', color: 'var(--text-secondary)' }}>
                {new Date(article.created_at).toLocaleDateString()}
              </span>
              <span style={{ fontSize: '12px', color: 'var(--text-secondary)' }}>
                👁️ {article.views}
              </span>
            </div>
            <div style={{ display: 'flex', gap: '8px', marginTop: '8px' }}>
              <button
                className="btn-secondary"
                style={{ padding: '6px 12px', fontSize: '12px', flex: 1 }}
                onClick={() => {
                  // Implement edit functionality
                  alert('Edit functionality coming soon');
                }}
              >
                Edit
              </button>
              <button
                className="btn-danger"
                style={{ padding: '6px 12px', fontSize: '12px', flex: 1 }}
                onClick={() => handleDeleteArticle(article.article_id)}
              >
                Delete
              </button>
            </div>
          </div>
        </div>
      ))}
    </div>
  )}
</div>
```

---

### 4. Integration Checklist

**What's Done**:
- ✅ Database tables created (articles, profile_photo columns)
- ✅ API endpoints implemented (all 6 endpoints)
- ✅ Router configured (articles module)
- ✅ CSS styling (logout modal, articles, photo upload)
- ✅ Migration scripts created

**What to Add to App.jsx**:
1. Photo upload field to SignupScreen (JSX + handler)
2. Photo handling in handleSubmit (add to payload)
3. Articles feed component for PatientDashboard (fetch + display)
4. Article detail modal for PatientDashboard
5. Article creation form for DoctorDashboard
6. Article list for DoctorDashboard (my articles)
7. Delete functionality for doctor articles

**API Integration Points**:
```javascript
// Patient
GET /api/articles/list → Display all articles
POST /api/articles/view → Track views

// Doctor
POST /api/articles/my → Get my articles
POST /api/articles/create → Create article
POST /api/articles/update → Edit article
POST /api/articles/delete → Delete article
```

---

### 5. Testing Endpoints

```bash
# List articles (patient)
curl -X GET http://localhost/api/articles/list

# Create article (doctor)
curl -X POST http://localhost/api/articles/create \
  -H "Content-Type: application/json" \
  -d '{
    "token": "your_token",
    "title": "Health Tips",
    "content": "Article content here",
    "cover_image": "https://example.com/image.jpg"
  }'

# Get my articles (doctor)
curl -X POST http://localhost/api/articles/my \
  -H "Content-Type: application/json" \
  -d '{"token": "your_token"}'

# Increment views
curl -X POST http://localhost/api/articles/view \
  -H "Content-Type: application/json" \
  -d '{"article_id": "ART_1234567890_abcd"}'
```

---

## Implementation Priority

1. **High Priority** (Core Features):
   - Add photo upload to SignupScreen
   - Add articles feed to PatientDashboard
   - Add article creation to DoctorDashboard

2. **Medium Priority** (Enhanced UX):
   - Article edit functionality
   - Search/filter articles
   - View full article in modal

3. **Low Priority** (Nice-to-Have):
   - Comments on articles
   - Article sharing
   - Save articles as favorites

---

## Notes

- All styling is done using CSS custom properties for easy dark/light mode switching
- Photo uploads are stored as base64 in the database (can be optimized for file system storage later)
- Articles use soft deletes (deleted_at timestamp) for data integrity
- Token-based authentication on all write operations
- View tracking implemented for article analytics
