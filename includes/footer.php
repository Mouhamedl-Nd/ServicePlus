<?php $racine = $racine ?? ''; ?>
<footer>
  <div class="footer-inner">
    <div class="footer-grid">
      <div>
        <div class="footer-logo">
          <div class="footer-logo-icon">🏠</div>
          <div class="footer-logo-text"><span>Services</span>Plus</div>
        </div>
        <p class="footer-desc">La plateforme qui met en relation clients et prestataires de services à domicile au Sénégal.</p>
        <p class="footer-credit">UNCHK — Master 1 Dev. Web &amp; Mobile — Promo 8 — 2025/2026</p>
      </div>
      <div class="footer-col">
        <h4>Services</h4>
        <ul>
          <li>Plomberie</li><li>Électricité</li><li>Ménage</li>
          <li>Jardinage</li><li>Peinture</li><li>Climatisation</li>
        </ul>
      </div>
      <div class="footer-col">
        <h4>Plateforme</h4>
        <ul>
          <li onclick="location.href='<?= $racine ?>index.php'">Comment ça marche</li>
          <li onclick="location.href='<?= $racine ?>inscription.php'">Devenir prestataire</li>
          <li onclick="location.href='<?= $racine ?>faq.php'">FAQ</li>
          <li onclick="location.href='<?= $racine ?>contact.php'">Contact</li>
        </ul>
      </div>
      <div class="footer-col">
        <h4>Contact</h4>
        <ul>
          <li>📧 contact@servicesplus.sn</li>
          <li>📞 +221 33 000 00 00</li>
          <li>📍 Dakar, Sénégal</li>
          <li>💬 Chat en ligne</li>
        </ul>
      </div>
    </div>
    <div class="footer-bottom">
      <span>© 2026 <b>ServicesPlus</b>. Tous droits réservés.</span>
      <span>Fait avec ❤️ par le <b>Groupe UNCHK — Master 1</b></span>
    </div>
  </div>
</footer>
</body>
</html>