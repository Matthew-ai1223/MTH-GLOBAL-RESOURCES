/**
 * MTH Bot AI Assistant Widget
 */
const AiWidget = {
  history: [],
  isOpen: false,

  init() {
    this.createStyles();
    this.render();
  },

  createStyles() {
    if (document.getElementById('ai-widget-styles')) return;
    const style = document.createElement('style');
    style.id = 'ai-widget-styles';
    style.textContent = `
      .ai-bubble {
        position: fixed;
        bottom: 2rem;
        right: 2rem;
        width: 60px;
        height: 60px;
        background: var(--primary);
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        z-index: 9999;
        transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
      }
      .ai-bubble:hover { transform: scale(1.1); }
      .ai-bubble i { font-size: 1.5rem; }

      .ai-window {
        position: fixed;
        bottom: 6rem;
        right: 2rem;
        width: 350px;
        height: 500px;
        background: white;
        border-radius: var(--radius-lg);
        box-shadow: 0 12px 48px rgba(0,0,0,0.15);
        z-index: 9998;
        display: none;
        flex-direction: column;
        overflow: hidden;
        border: 1px solid var(--border-color);
        font-family: 'Inter', sans-serif;
      }
      .ai-window.open { display: flex; }

      .ai-header {
        background: var(--primary);
        color: white;
        padding: 1.25rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
      }
      .ai-header h3 { margin: 0; font-size: 1.1rem; flex: 1; }
      
      .ai-chat {
        flex: 1;
        overflow-y: auto;
        padding: 1rem;
        display: flex;
        flex-direction: column;
        gap: 1rem;
        background: #f9fafb;
      }
      
      .ai-suggestions {
        padding: 0.75rem 1rem;
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        background: white;
        border-top: 1px solid var(--border-color);
        max-height: 150px;
        overflow-y: auto;
      }
      .ai-suggestions::-webkit-scrollbar { width: 4px; }
      .ai-suggestions::-webkit-scrollbar-thumb { background: #e5e7eb; border-radius: 4px; }
      
      .suggestion-chip {
        background: #f3f4f6;
        border: 1px solid #e5e7eb;
        padding: 0.5rem 0.9rem;
        border-radius: 99px;
        font-size: 0.75rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        color: #374151;
        display: flex;
        align-items: center;
        gap: 0.4rem;
      }
      .suggestion-chip:hover { 
        background: var(--primary); 
        color: white; 
        border-color: var(--primary);
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
      }

      .msg {
        max-width: 85%;
        padding: 0.75rem 1rem;
        border-radius: 1rem;
        font-size: 0.9rem;
        line-height: 1.5;
      }
      .msg-bot {
        background: white;
        color: var(--text-main);
        align-self: flex-start;
        border-bottom-left-radius: 0.25rem;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
      }
      .msg-user {
        background: var(--primary);
        color: white;
        align-self: flex-end;
        border-bottom-right-radius: 0.25rem;
      }
      
      .ai-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        background: var(--primary);
        color: white !important;
        padding: 0.6rem 1.2rem;
        border-radius: 8px;
        text-decoration: none !important;
        font-weight: 600;
        font-size: 0.85rem;
        margin: 0.75rem 0;
        transition: var(--transition);
        border: none;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
      }
      .ai-btn:hover { background: #1e3a8a; transform: translateY(-1px); box-shadow: 0 4px 8px rgba(0,0,0,0.15); }

      .ai-input-area {
        padding: 1rem;
        background: white;
        border-top: 1px solid var(--border-color);
        display: flex;
        gap: 0.5rem;
      }
      .ai-input-area input {
        flex: 1;
        padding: 0.75rem;
        border: 1px solid var(--border-color);
        border-radius: var(--radius-md);
        font-size: 0.9rem;
      }
      .ai-input-area input:focus { outline: none; border-color: var(--primary); }
      .ai-input-area button {
        background: var(--primary);
        color: white;
        border: none;
        width: 40px;
        height: 40px;
        border-radius: var(--radius-md);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
      }

      @media (max-width: 450px) {
        .ai-window {
          width: calc(100vw - 2rem);
          right: 1rem;
          bottom: 5.5rem;
          height: calc(100vh - 12rem);
        }
      }
    `;
    document.head.appendChild(style);
  },

  render() {
    const container = document.createElement('div');
    container.innerHTML = `
      <div class="ai-bubble" id="aiBubble">
        <i class="fa-solid fa-robot"></i>
      </div>
      <div class="ai-window" id="aiWindow">
        <div class="ai-header">
          <i class="fa-solid fa-droplet"></i>
          <h3>MTH Bot Assistant</h3>
          <button onclick="AiWidget.toggle()" style="background:none;border:none;color:white;cursor:pointer;"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="ai-chat" id="aiChat">
          <div class="msg msg-bot">
            Hello! I'm MTH Bot 💧. How can I help you today?
          </div>
        </div>
        <div class="ai-suggestions" id="aiSuggestions">
          <div class="suggestion-chip" onclick="AiWidget.suggest('What is in stock?')"><i class="fa-solid fa-droplet"></i> What's in stock?</div>
          <div class="suggestion-chip" onclick="AiWidget.suggest('Track my last order')"><i class="fa-solid fa-truck-fast"></i> Track my order</div>
          <div class="suggestion-chip" onclick="AiWidget.suggest('Do you sell Coca-Cola?')"><i class="fa-solid fa-bottle-water"></i> Coca-Cola?</div>
          <div class="suggestion-chip" onclick="AiWidget.suggest('Where do you deliver?')"><i class="fa-solid fa-location-dot"></i> Delivery areas</div>
          <div class="suggestion-chip" onclick="AiWidget.suggest('Can I pay on delivery?')"><i class="fa-solid fa-hand-holding-dollar"></i> Pay on Delivery?</div>
          <div class="suggestion-chip" onclick="AiWidget.suggest('How do I contact support?')"><i class="fa-solid fa-headset"></i> Contact Support</div>
          <div class="suggestion-chip" onclick="AiWidget.suggest('Help me with my account')"><i class="fa-solid fa-user-gear"></i> Account Help</div>
        </div>
        <div class="ai-input-area">
          <input type="text" id="aiInput" placeholder="Ask me anything...">
          <button onclick="AiWidget.send()"><i class="fa-solid fa-paper-plane"></i></button>
        </div>
      </div>
    `;
    document.body.appendChild(container);

    document.getElementById('aiBubble').onclick = () => this.toggle();
    document.getElementById('aiInput').onkeyup = (e) => {
      if (e.key === 'Enter') this.send();
    };
  },

  toggle() {
    this.isOpen = !this.isOpen;
    document.getElementById('aiWindow').classList.toggle('open', this.isOpen);
    if (this.isOpen) {
      document.getElementById('aiInput').focus();
      // Hide suggestions if chat is long
      this.updateSuggestions();
    }
  },

  suggest(text) {
    document.getElementById('aiInput').value = text;
    this.send();
  },

  updateSuggestions() {
    const suggs = document.getElementById('aiSuggestions');
    if (this.history.length > 2) {
      suggs.style.display = 'none';
    } else {
      suggs.style.display = 'flex';
    }
  },

  async send() {
    const input = document.getElementById('aiInput');
    const msg = input.value.trim();
    if (!msg) return;

    input.value = '';
    this.addMsg(msg, 'user');

    // Add loading state
    const chat = document.getElementById('aiChat');
    const loading = document.createElement('div');
    loading.className = 'msg msg-bot loading';
    loading.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Thinking...';
    chat.appendChild(loading);
    chat.scrollTop = chat.scrollHeight;

    try {
      // Gather Context
      let context = {
        currentPage: window.location.href,
        timestamp: new Date().toISOString()
      };

      try {
        const user = await api("auth.me");
        context.user = user;
        // If user is logged in, maybe get recent orders
        if (user && user.role === 'customer') {
          const orders = await api("orders.list");
          context.recentOrders = orders.slice(0, 3); // Just the latest 3
        }
      } catch(e) {
        context.user = "Anonymous (Not Logged In)";
      }

      const res = await api("ai.chat", "POST", {
        message: msg,
        history: this.history,
        context: context
      });
      
      loading.remove();
      this.addMsg(res.reply, 'bot');
      this.history.push({ role: 'user', content: msg });
      this.history.push({ role: 'assistant', content: res.reply });
      
      // Limit history to last 10 messages
      if (this.history.length > 10) this.history = this.history.slice(-10);

      this.updateSuggestions();
    } catch (err) {
      loading.innerHTML = 'Sorry, I lost my connection to the server. 💧';
      console.error(err);
    }
  },

  addMsg(text, sender) {
    const chat = document.getElementById('aiChat');
    const div = document.createElement('div');
    div.className = `msg msg-${sender}`;
    div.innerHTML = this.formatText(text);
    chat.appendChild(div);
    chat.scrollTop = chat.scrollHeight;
  },

  formatText(text) {
    // 1. Handle Bold (**text**)
    text = text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
    
    // 2. Handle Bullet Points (* Item)
    // We look for asterisks at the start of a line or after a newline
    text = text.replace(/(?:\r\n|\r|\n|^)\*\s+(.*?)(?=\r\n|\r|\n|$)/g, '<div style="margin-left: 10px; padding: 2px 0;">• $1</div>');

    // 3. Handle Buttons [[Label|/url]]
    text = text.replace(/\[\[(.*?)\|(.*?)\]\]/g, (match, label, url) => {
      const b = window.BASE_URL || '/';
      const fullUrl = url.startsWith('http') ? url : (b + url.replace(/^\//, ''));
      return `<a href="${fullUrl}" class="ai-btn"><i class="fa-solid fa-arrow-right-to-bracket"></i> ${label}</a>`;
    });

    // 4. Handle Newlines
    text = text.replace(/\n/g, '<br>');

    return text;
  }
};

// Initialize if on a page
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => AiWidget.init());
} else {
  AiWidget.init();
}
