const BASE_URL = "https://your-base-url.com"

// Format tab switching in write page
document.addEventListener("DOMContentLoaded", () => {
  const formatTabs = document.querySelectorAll(".format-tab")
  const formatContents = document.querySelectorAll(".format-content")
  const formatInput = document.getElementById("format")

  formatTabs.forEach((tab) => {
    tab.addEventListener("click", function (e) {
      e.preventDefault()
      const format = this.dataset.format

      // Update active tab
      formatTabs.forEach((t) => t.classList.remove("active"))
      this.classList.add("active")

      // Update active content
      formatContents.forEach((c) => c.classList.remove("active"))
      document.getElementById(format + "Format").classList.add("active")

      // Update hidden input
      if (formatInput) {
        formatInput.value = format
      }
    })
  })
})

// Toggle like functionality
function toggleLike(poemId) {
  const likeBtn = document.getElementById("likeBtn")
  const likeCount = document.getElementById("likeCount")

  fetch(BASE_URL + "/api/likes.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify({ poem_id: poemId }),
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        likeCount.textContent = data.likes_count
        if (data.liked) {
          likeBtn.classList.remove("btn-secondary")
          likeBtn.classList.add("btn-liked")
        } else {
          likeBtn.classList.remove("btn-liked")
          likeBtn.classList.add("btn-secondary")
        }
      }
    })
    .catch((error) => console.error("Error:", error))
}

// Submit comment functionality
function submitComment(event, poemId) {
  event.preventDefault()

  const contentInput = document.getElementById("commentContent")
  const content = contentInput.value.trim()

  if (!content) return

  fetch(BASE_URL + "/api/comments.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify({
      poem_id: poemId,
      content: content,
    }),
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        // Clear input
        contentInput.value = ""

        // Add comment to list
        const commentsList = document.getElementById("commentsList")
        const commentHtml = createCommentHTML(data.comment)
        commentsList.insertAdjacentHTML("afterbegin", commentHtml)
      }
    })
    .catch((error) => console.error("Error:", error))
}

// Helper function to create comment HTML
function createCommentHTML(comment) {
  const date = new Date(comment.created_at)
  const formattedDate = date.toLocaleDateString("en-US", {
    month: "short",
    day: "numeric",
    year: "numeric",
    hour: "numeric",
    minute: "2-digit",
  })

  const initial = comment.username.charAt(0).toUpperCase()

  return `
        <div class="comment">
            <div class="comment-header">
                <div class="author-avatar-small">
                    ${initial}
                </div>
                <div>
                    <a href="${BASE_URL}/profile?user=${comment.username}" class="comment-author">
                        ${comment.username}
                    </a>
                    <div class="comment-date">
                        ${formattedDate}
                    </div>
                </div>
            </div>
            <div class="comment-content">
                ${escapeHtml(comment.content).replace(/\n/g, "<br>")}
            </div>
        </div>
    `
}

// Helper function to escape HTML
function escapeHtml(text) {
  const div = document.createElement("div")
  div.textContent = text
  return div.innerHTML
}
